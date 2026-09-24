<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\MahjongPoinEntry;
use App\Models\Turnamen;
use App\Models\TurnamenGrupPendaftaran;
use App\Models\TurnamenKategori;
use App\Models\TurnamenMeja;
use App\Models\TurnamenMejaSeat;
use App\Models\TurnamenPeserta;
use App\Services\Concerns\ResolvesTurnamenKategori;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MahjongTeamMatchmakingService
{
    use ResolvesTurnamenKategori;

    public const PLAYERS_PER_TEAM = 4;

    /** @var list<int> */
    public const ALLOWED_START_TEAM_COUNTS = [4, 8];

    protected MahjongTeamSeatingService $seating;

    protected MahjongTeamStandingRanker $ranker;

    public function __construct(
        MahjongTeamSeatingService $seating,
        MahjongTeamStandingRanker $ranker
    ) {
        $this->seating = $seating;
        $this->ranker = $ranker;
    }

    public function canGenerateTeams(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjongTeam()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && ! $kategori->activeGrup()->exists();
    }

    public function hasValidStartingRoster(Turnamen $turnamen, $idKategori = null): bool
    {
        try {
            $this->assertValidStartingRoster($turnamen, $idKategori);

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    public function assertValidStartingRoster(Turnamen $turnamen, $idKategori = null): void
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $count = $this->approvedEntries($turnamen, $kategori->id)->count();
        $perTeam = $this->playersPerTeam($turnamen, $kategori->id);

        if ($perTeam < 1 || $count % $perTeam !== 0) {
            throw new RuntimeException('Jumlah pemain approved harus kelipatan '.$perTeam.'.');
        }

        $teamCount = (int) ($count / $perTeam);

        if (! in_array($teamCount, self::ALLOWED_START_TEAM_COUNTS, true)) {
            throw new RuntimeException(sprintf(
                'Jumlah tim awal harus 4 atau 8 (%d atau %d pemain).',
                $perTeam * 4,
                $perTeam * 8
            ));
        }
    }

    public function canReshuffleMeja(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjongTeam()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && $kategori->activeGrup()->count() >= 2
            && TurnamenMeja::query()
                ->where('id_kategori', $kategori->id)
                ->where('is_aktif', true)
                ->exists();
    }

    public function canAdvanceBabak(Turnamen $turnamen, $idKategori = null): bool
    {
        return $this->canReshuffleMeja($turnamen, $idKategori);
    }

    public function canComplete(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjongTeam()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && $kategori->activeGrup()->count() === 1;
    }

    public function canEditTeams(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if (! $turnamen->isMahjongTeam() || ! $this->isCompetitionOngoing($turnamen, $kategori->id)) {
            return false;
        }

        if (! $kategori->activeGrup()->exists()) {
            return false;
        }

        return ! MahjongPoinEntry::query()
            ->whereHas('grupMember.grup', function ($q) use ($kategori) {
                $q->where('id_kategori', $kategori->id)->where('is_aktif', true);
            })
            ->exists();
    }

    public function canReset(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjongTeam()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && (
                $kategori->grup()->exists()
                || TurnamenMeja::query()->where('id_kategori', $kategori->id)->exists()
            );
    }

    /**
     * @return array{teams: list<array<string, mixed>>, meja: list<array<string, mixed>>, babak: int, ronde: int}
     */
    public function generateTeams(Turnamen $turnamen, string $mode = 'random', $idKategori = null): array
    {
        if (! $this->canGenerateTeams($turnamen, $idKategori)) {
            throw new RuntimeException('Tim Mahjong belum dapat dibuat.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $entries = $this->approvedEntries($turnamen, $kategori->id);
        $this->assertValidStartingRoster($turnamen, $kategori->id);
        $perTeam = $this->playersPerTeam($turnamen, $kategori->id);

        return DB::transaction(function () use ($turnamen, $kategori, $entries, $mode, $perTeam) {
            $this->deleteAllMeja($kategori->id);
            $kategori->grup()->delete();

            $entryById = $entries->keyBy(fn (TurnamenPeserta $entry) => (int) $entry->id);
            $usedPesertaIds = [];
            $usedNamesLower = [];
            $babak = 1;
            $teams = [];

            foreach ($this->completeRegistrationTeams($turnamen, $kategori) as $preTeam) {
                $teamEntries = collect();

                foreach ($preTeam->members as $member) {
                    $entry = $entryById->get((int) $member->id_peserta);
                    if (! $entry) {
                        $teamEntries = collect();
                        break;
                    }
                    $teamEntries->push($entry);
                }

                if ($teamEntries->count() !== $perTeam) {
                    continue;
                }

                $grup = Grup::create([
                    'id_turnamen' => $turnamen->id,
                    'id_kategori' => $kategori->id,
                    'nama' => $preTeam->nama,
                    'babak' => $babak,
                    'ronde' => 1,
                    'is_aktif' => true,
                ]);

                foreach ($teamEntries as $entry) {
                    GrupMember::create([
                        'id_grup' => $grup->id,
                        'id_pemain' => $entry->id_pemain1,
                        'id_turnamen_peserta' => $entry->id,
                        'poin_didapat' => 0,
                        'poin_akumulasi' => 0,
                    ]);
                    $usedPesertaIds[] = (int) $entry->id;
                }

                $usedNamesLower[mb_strtolower(trim($grup->nama))] = true;
                $teams[] = [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'pemain_count' => $teamEntries->count(),
                    'from_registration' => true,
                ];
            }

            $remaining = $entries
                ->reject(fn (TurnamenPeserta $entry) => in_array((int) $entry->id, $usedPesertaIds, true))
                ->values();

            $ordered = $mode === 'by_rating'
                ? $remaining->sortByDesc(fn (TurnamenPeserta $e) => optional($e->pemain1)->rating ?? 0)->values()
                : $remaining->shuffle()->values();

            $chunks = $ordered->chunk($perTeam)->values();
            $generatedIndex = 0;

            foreach ($chunks as $teamEntries) {
                $nama = $this->nextUnusedTeamName($usedNamesLower, $generatedIndex);
                $grup = Grup::create([
                    'id_turnamen' => $turnamen->id,
                    'id_kategori' => $kategori->id,
                    'nama' => $nama,
                    'babak' => $babak,
                    'ronde' => 1,
                    'is_aktif' => true,
                ]);

                foreach ($teamEntries as $entry) {
                    GrupMember::create([
                        'id_grup' => $grup->id,
                        'id_pemain' => $entry->id_pemain1,
                        'id_turnamen_peserta' => $entry->id,
                        'poin_didapat' => 0,
                        'poin_akumulasi' => 0,
                    ]);
                }

                $teams[] = [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'pemain_count' => $teamEntries->count(),
                    'from_registration' => false,
                ];
            }

            $mejaResult = $this->createMejaForActiveTeams($turnamen, $kategori, $babak);

            return [
                'teams' => $teams,
                'meja' => $mejaResult['meja'],
                'babak' => $babak,
                'ronde' => $mejaResult['ronde'],
            ];
        });
    }

    /**
     * Reshuffle tables in the same babak. Points accumulate (poin_didapat kept).
     *
     * @return array{meja: list<array<string, mixed>>, babak: int, ronde: int}
     */
    public function reshuffleMeja(Turnamen $turnamen, $idKategori = null): array
    {
        if (! $this->canReshuffleMeja($turnamen, $idKategori)) {
            throw new RuntimeException('Meja belum dapat diacak ulang.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $babak = (int) ($kategori->activeGrup()->max('babak') ?: 1);

        return DB::transaction(function () use ($turnamen, $kategori, $babak) {
            $this->deactivateActiveMeja($kategori->id);

            return $this->createMejaForActiveTeams($turnamen, $kategori, $babak);
        });
    }

    /**
     * @param  list<array{id: int, poin: int}>  $scores
     */
    public function addMejaPointEntries(
        TurnamenMeja $meja,
        array $scores,
        ?int $winnerMemberId = null
    ): Collection {
        $turnamen = $meja->turnamen ?? Turnamen::find($meja->id_turnamen);

        if (! $turnamen || ! $turnamen->isMahjongTeam()) {
            throw new RuntimeException('Input poin hanya untuk Mahjong Tim.');
        }

        if (! $meja->is_aktif) {
            throw new RuntimeException('Meja tidak aktif.');
        }

        $meja->loadMissing('seats.grupMember');

        if ($meja->seats->count() !== MahjongTeamSeatingService::TABLE_SIZE) {
            throw new RuntimeException('Meja harus berisi 4 pemain.');
        }

        if (count($scores) !== MahjongTeamSeatingService::TABLE_SIZE) {
            throw new RuntimeException('Harus mengisi poin untuk 4 pemain di meja.');
        }

        $seatMemberIds = $meja->seats->pluck('id_grup_member')->map(fn ($id) => (int) $id)->all();

        if ($winnerMemberId !== null && ! in_array($winnerMemberId, $seatMemberIds, true)) {
            throw new RuntimeException('Pemenang harus salah satu pemain di meja.');
        }

        return DB::transaction(function () use ($meja, $scores, $winnerMemberId, $seatMemberIds) {
            foreach ($scores as $row) {
                $memberId = (int) ($row['id'] ?? 0);
                $poin = (int) ($row['poin'] ?? 0);

                if (! in_array($memberId, $seatMemberIds, true)) {
                    throw new RuntimeException('Pemain tidak duduk di meja ini.');
                }

                $member = GrupMember::findOrFail($memberId);
                $isWinner = $winnerMemberId !== null && $memberId === $winnerMemberId;

                MahjongPoinEntry::create([
                    'id_grup_member' => $member->id,
                    'id_meja' => $meja->id,
                    'poin' => $poin,
                    'is_winner' => $isWinner,
                ]);
            }

            return $meja->seats->map(function ($seat) {
                $member = $seat->grupMember;
                if (! $member) {
                    return null;
                }

                $this->syncPoinDidapatFromEntries($member);

                return $member->fresh(['poinEntries', 'pemain', 'turnamenPeserta.pemain1', 'grup']);
            })->filter()->values();
        });
    }

    /**
     * @param  list<array{id: int, entry_id: int, poin: int}>  $scores
     * @return Collection<int, GrupMember>
     */
    public function updateMejaPointEntries(
        TurnamenMeja $meja,
        array $scores,
        ?int $winnerMemberId = null
    ): Collection {
        $turnamen = $meja->turnamen ?? Turnamen::find($meja->id_turnamen);

        if (! $turnamen || ! $turnamen->isMahjongTeam()) {
            throw new RuntimeException('Input poin hanya untuk Mahjong Tim.');
        }

        $meja->loadMissing(['seats.grupMember.poinEntries']);

        $membersById = $meja->seats
            ->map(fn ($seat) => $seat->grupMember)
            ->filter()
            ->keyBy('id');

        if ($membersById->count() !== MahjongTeamSeatingService::TABLE_SIZE) {
            throw new RuntimeException('Meja harus berisi 4 pemain.');
        }

        if (count($scores) !== MahjongTeamSeatingService::TABLE_SIZE) {
            throw new RuntimeException('Harus mengisi poin untuk 4 pemain di meja.');
        }

        $scoreIds = collect($scores)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $memberIds = $membersById->keys()->map(fn ($id) => (int) $id)->sort()->values();

        if ($scoreIds->all() !== $memberIds->all()) {
            throw new RuntimeException('Daftar pemain tidak cocok dengan kursi meja.');
        }

        if ($winnerMemberId !== null && ! $membersById->has($winnerMemberId)) {
            throw new RuntimeException('Pemenang harus salah satu pemain di meja.');
        }

        $entryIds = collect($scores)->pluck('entry_id')->map(fn ($id) => (int) $id)->filter()->unique();
        if ($entryIds->count() !== MahjongTeamSeatingService::TABLE_SIZE) {
            throw new RuntimeException('Setiap pemain harus punya entri poin yang valid untuk ronde ini.');
        }

        return DB::transaction(function () use ($meja, $scores, $winnerMemberId, $membersById) {
            foreach ($scores as $score) {
                $memberId = (int) $score['id'];
                $entryId = (int) ($score['entry_id'] ?? 0);
                $member = $membersById->get($memberId);
                $entry = $member->poinEntries->firstWhere('id', $entryId);

                if (! $entry || (int) $entry->id_meja !== (int) $meja->id) {
                    throw new RuntimeException('Entri poin tidak ditemukan untuk pemain di meja ini.');
                }

                $entry->update([
                    'poin' => (int) $score['poin'],
                    'is_winner' => $winnerMemberId !== null && $memberId === $winnerMemberId,
                ]);
            }

            return $membersById->values()->map(function (GrupMember $member) {
                $this->syncPoinDidapatFromEntries($member);

                return $member->fresh(['poinEntries', 'pemain', 'turnamenPeserta.pemain1', 'grup']);
            })->values();
        });
    }

    /**
     * @param  list<array{id: int, poin: int}>  $scores
     * @return Collection<int, GrupMember>
     */
    public function updateMejaAdjustments(TurnamenMeja $meja, array $scores): Collection
    {
        $turnamen = $meja->turnamen ?? Turnamen::find($meja->id_turnamen);

        if (! $turnamen || ! $turnamen->isMahjongTeam()) {
            throw new RuntimeException('Bonus/penalti hanya untuk Mahjong Tim.');
        }

        if (! $meja->is_aktif) {
            throw new RuntimeException('Meja tidak aktif.');
        }

        $meja->loadMissing('seats.grupMember');

        $membersById = $meja->seats
            ->map(fn ($seat) => $seat->grupMember)
            ->filter()
            ->keyBy('id');

        if ($membersById->count() !== MahjongTeamSeatingService::TABLE_SIZE) {
            throw new RuntimeException('Meja harus berisi 4 pemain.');
        }

        if (count($scores) !== MahjongTeamSeatingService::TABLE_SIZE) {
            throw new RuntimeException('Bonus/penalti harus diisi untuk 4 pemain di meja.');
        }

        $scoreIds = collect($scores)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $memberIds = $membersById->keys()->map(fn ($id) => (int) $id)->sort()->values();

        if ($scoreIds->all() !== $memberIds->all()) {
            throw new RuntimeException('Daftar pemain tidak cocok dengan kursi meja.');
        }

        return DB::transaction(function () use ($scores, $membersById) {
            foreach ($scores as $score) {
                $member = $membersById->get((int) $score['id']);
                $member->update(['poin_penyesuaian' => (int) $score['poin']]);
            }

            return $membersById->values()->map(function (GrupMember $member) {
                return $member->fresh(['poinEntries', 'pemain', 'turnamenPeserta.pemain1', 'grup']);
            })->values();
        });
    }

    public function addMemberPointEntry(GrupMember $member, int $poin): GrupMember
    {
        $this->assertActiveMahjongTeamMember($member);

        $meja = $this->activeMejaForMember($member);

        if (! $meja) {
            throw new RuntimeException('Pemain tidak duduk di meja aktif.');
        }

        MahjongPoinEntry::create([
            'id_grup_member' => $member->id,
            'id_meja' => $meja->id,
            'poin' => $poin,
            'is_winner' => false,
        ]);

        $this->syncPoinDidapatFromEntries($member);

        return $member->fresh(['poinEntries', 'pemain', 'turnamenPeserta.pemain1', 'grup']);
    }

    public function updateMemberPointEntry(GrupMember $member, MahjongPoinEntry $entry, int $poin): GrupMember
    {
        $this->assertActiveMahjongTeamMember($member);

        if ((int) $entry->id_grup_member !== (int) $member->id) {
            throw new RuntimeException('Entri poin tidak cocok dengan anggota tim.');
        }

        $entry->update(['poin' => $poin]);
        $this->syncPoinDidapatFromEntries($member);

        return $member->fresh(['poinEntries', 'pemain', 'turnamenPeserta.pemain1', 'grup']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function teamStandings(Turnamen $turnamen, $idKategori = null): Collection
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        $teams = $kategori->activeGrup()
            ->with(['members.poinEntries', 'members.pemain', 'members.turnamenPeserta.pemain1'])
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        return $teams->map(function (Grup $tim) {
            $total = (int) $tim->members->sum(fn (GrupMember $m) => (int) $m->poin_didapat);

            return [
                'id_tim' => (int) $tim->id,
                'nama' => $tim->nama,
                'total_poin' => $total,
                'members' => $tim->members
                    ->sort(function (GrupMember $a, GrupMember $b) {
                        $cmp = ((int) $b->poin_didapat) <=> ((int) $a->poin_didapat);

                        return $cmp !== 0 ? $cmp : ((int) $a->id) <=> ((int) $b->id);
                    })
                    ->values()
                    ->map(fn (GrupMember $m) => [
                        'id' => (int) $m->id,
                        'id_pemain' => $m->id_pemain ? (int) $m->id_pemain : null,
                        'nama' => $m->display_name,
                        'poin_didapat' => (int) $m->poin_didapat,
                    ])
                    ->all(),
            ];
        })->sort(function (array $a, array $b) {
            $cmp = ((int) $b['total_poin']) <=> ((int) $a['total_poin']);

            return $cmp !== 0 ? $cmp : ((int) $a['id_tim']) <=> ((int) $b['id_tim']);
        })->values();
    }

    /**
     * @param  list<int>|null  $tiebreakTimIds
     * @return array<string, mixed>
     */
    public function previewAdvanceTeams(
        Turnamen $turnamen,
        int $jumlahTimLolos,
        $idKategori = null,
        ?array $tiebreakTimIds = null
    ): array {
        $selection = $this->resolveAdvanceSelection(
            $turnamen,
            $jumlahTimLolos,
            $idKategori,
            $tiebreakTimIds
        );

        if (! empty($selection['needs_tiebreak'])) {
            return $selection;
        }

        $currentBabak = (int) ($selection['current_babak'] ?? 1);
        $qualifiers = collect($selection['qualifier_rows'] ?? [])->values();

        foreach ($qualifiers as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return [
            'preview' => true,
            'jumlah_lolos' => $jumlahTimLolos,
            'current_babak' => $currentBabak,
            'next_babak' => $jumlahTimLolos === 1 ? $currentBabak : $currentBabak + 1,
            'is_champion' => $jumlahTimLolos === 1,
            'qualifiers' => $qualifiers->all(),
            'tiebreak_tim_ids' => $tiebreakTimIds,
        ];
    }

    /**
     * @param  list<int>|null  $tiebreakTimIds
     * @return array<string, mixed>
     */
    public function advanceTeams(
        Turnamen $turnamen,
        int $jumlahTimLolos,
        $idKategori = null,
        ?array $tiebreakTimIds = null
    ): array {
        $selection = $this->resolveAdvanceSelection(
            $turnamen,
            $jumlahTimLolos,
            $idKategori,
            $tiebreakTimIds
        );

        if (! empty($selection['needs_tiebreak'])) {
            return $selection;
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $qualifierIds = collect($selection['qualifier_rows'] ?? [])
            ->map(fn (array $row) => (int) ($row['id_tim'] ?? 0))
            ->filter()
            ->values()
            ->all();

        return DB::transaction(function () use ($turnamen, $kategori, $qualifierIds, $jumlahTimLolos, $selection) {
            $this->deactivateActiveMeja($kategori->id);

            $activeTeams = $kategori->activeGrup()->get();
            foreach ($activeTeams as $team) {
                if (! in_array((int) $team->id, $qualifierIds, true)) {
                    $team->update(['is_aktif' => false]);
                }
            }

            if ($jumlahTimLolos === 1) {
                $this->updateCompetitionLifecycle($kategori, [
                    'mahjong_is_final' => true,
                ]);

                return [
                    'is_champion' => true,
                    'champion_tim_id' => $qualifierIds[0] ?? null,
                    'qualifiers' => 1,
                    'babak' => (int) ($selection['current_babak'] ?? 1),
                    'meja' => [],
                ];
            }

            $nextBabak = (int) ($selection['current_babak'] ?? 1) + 1;

            $advancing = $kategori->activeGrup()
                ->whereIn('id', $qualifierIds)
                ->with('members')
                ->get();

            foreach ($advancing as $team) {
                $team->update(['babak' => $nextBabak, 'ronde' => 1]);

                foreach ($team->members as $member) {
                    $member->update([
                        'poin_didapat' => 0,
                        'poin_akumulasi' => 0,
                    ]);
                }
            }

            $mejaResult = $this->createMejaForActiveTeams($turnamen, $kategori, $nextBabak);

            return [
                'is_champion' => false,
                'qualifiers' => count($qualifierIds),
                'babak' => $nextBabak,
                'ronde' => $mejaResult['ronde'],
                'meja' => $mejaResult['meja'],
            ];
        });
    }

    /**
     * @return list<array{place: int, pemain_ids: list<int>, peserta_id: int|null, total_poin: int, nama: string}>
     */
    public function resolveChampionTeamMembers(Turnamen $turnamen, $idKategori = null): array
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $team = $kategori->activeGrup()->with(['members.pemain', 'members.turnamenPeserta'])->first();

        if (! $team) {
            return [];
        }

        $placements = [];

        foreach ($team->members as $member) {
            $placements[] = [
                'place' => 1,
                'pemain_ids' => [(int) $member->id_pemain],
                'peserta_id' => $member->id_turnamen_peserta ? (int) $member->id_turnamen_peserta : null,
                'total_poin' => (int) $member->poin_didapat,
                'nama' => $member->display_name,
                'tim_nama' => $team->nama,
            ];
        }

        return $placements;
    }

    public function resetAll(Turnamen $turnamen, $idKategori = null): void
    {
        if (! $this->canReset($turnamen, $idKategori)) {
            throw new RuntimeException('Mahjong Tim tidak dapat direset.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        DB::transaction(function () use ($kategori, $turnamen) {
            $this->deleteAllMeja($kategori->id);
            $kategori->grup()->delete();
            $this->updateCompetitionLifecycle($kategori, [
                'mahjong_is_final' => false,
            ]);
            $turnamen->update(['mahjong_is_final' => false]);
        });
    }

    /**
     * Inactive meja history: babak → ronde → meja.
     *
     * @return Collection<int, array{babak: int, rondes: Collection}>
     */
    public function getMatchmakingHistory(Turnamen $turnamen, $idKategori = null): Collection
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        $mejaList = TurnamenMeja::query()
            ->where('id_kategori', $kategori->id)
            ->where('is_aktif', false)
            ->with([
                'seats.grupMember.pemain',
                'seats.grupMember.turnamenPeserta.pemain1',
                'seats.grupMember.grup',
                'poinEntries',
            ])
            ->orderByDesc('babak')
            ->orderBy('ronde')
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        if ($mejaList->isEmpty()) {
            return collect();
        }

        return $mejaList
            ->groupBy(fn (TurnamenMeja $meja) => (int) ($meja->babak ?: 1))
            ->sortKeysDesc()
            ->map(function (Collection $babakMeja, $babak) {
                $rondes = $babakMeja
                    ->groupBy(fn (TurnamenMeja $meja) => (int) ($meja->ronde ?: 1))
                    ->sortKeys()
                    ->map(fn (Collection $rondeMeja, $ronde) => [
                        'ronde' => (int) $ronde,
                        'meja' => $rondeMeja->values(),
                    ])
                    ->values();

                return [
                    'babak' => (int) $babak,
                    'rondes' => $rondes,
                ];
            })
            ->values();
    }

    /**
     * @param  list<int>|null  $tiebreakTimIds
     * @return array<string, mixed>
     */
    protected function resolveAdvanceSelection(
        Turnamen $turnamen,
        int $jumlahTimLolos,
        $idKategori = null,
        ?array $tiebreakTimIds = null
    ): array {
        if (! $this->canAdvanceBabak($turnamen, $idKategori)) {
            throw new RuntimeException('Babak Mahjong Tim tidak dapat dilanjutkan.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $currentBabak = (int) ($kategori->activeGrup()->max('babak') ?: 1);
        $teamCount = $kategori->activeGrup()->count();
        $allowed = $this->seating->allowedAdvanceCounts($teamCount);

        if (! in_array($jumlahTimLolos, $allowed, true)) {
            throw new RuntimeException(sprintf(
                'Jumlah tim lolos harus salah satu dari: %s.',
                implode(', ', $allowed)
            ));
        }

        $standings = $this->teamStandings($turnamen, $kategori->id);
        $selection = $this->ranker->resolveAdvanceTeams($standings, $jumlahTimLolos, $tiebreakTimIds);

        if (($selection['status'] ?? '') === 'needs_tiebreak') {
            return [
                'needs_tiebreak' => true,
                'jumlah_lolos' => $jumlahTimLolos,
                'current_babak' => $currentBabak,
                'slots_remaining' => (int) ($selection['slots_remaining'] ?? 0),
                'auto_qualified' => ($selection['auto_qualified'] ?? collect())->values()->all(),
                'contested' => ($selection['contested'] ?? collect())->values()->all(),
            ];
        }

        $qualifiers = $selection['qualifiers'] ?? collect();

        if ($qualifiers->count() !== $jumlahTimLolos) {
            throw new RuntimeException('Jumlah tim lolos tidak sesuai permintaan.');
        }

        return [
            'needs_tiebreak' => false,
            'current_babak' => $currentBabak,
            'qualifier_rows' => $qualifiers->values(),
        ];
    }

    /**
     * @return array{meja: list<array<string, mixed>>, babak: int, ronde: int}
     */
    protected function createMejaForActiveTeams(
        Turnamen $turnamen,
        TurnamenKategori $kategori,
        int $babak
    ): array {
        $teams = $kategori->activeGrup()
            ->with('members')
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        if ($teams->count() < 2) {
            throw new RuntimeException('Minimal 2 tim aktif untuk membentuk meja.');
        }

        $ronde = $this->nextMejaRonde($kategori->id, $babak);
        $plan = $this->seating->buildSeatPlan($teams);
        $mejaMeta = [];

        foreach ($plan as $index => $memberIds) {
            $meja = TurnamenMeja::create([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $kategori->id,
                'nama' => 'Meja '.($index + 1),
                'babak' => $babak,
                'ronde' => $ronde,
                'is_aktif' => true,
            ]);

            foreach ($memberIds as $seatOrder => $memberId) {
                TurnamenMejaSeat::create([
                    'id_meja' => $meja->id,
                    'id_grup_member' => $memberId,
                    'seat_order' => $seatOrder,
                ]);
            }

            $mejaMeta[] = [
                'id' => $meja->id,
                'nama' => $meja->nama,
                'seat_count' => count($memberIds),
            ];
        }

        return [
            'meja' => $mejaMeta,
            'babak' => $babak,
            'ronde' => $ronde,
        ];
    }

    protected function nextMejaRonde($idKategori, int $babak): int
    {
        $max = (int) TurnamenMeja::query()
            ->where('id_kategori', $idKategori)
            ->where('babak', $babak)
            ->max('ronde');

        return $max + 1;
    }

    protected function deactivateActiveMeja($idKategori): void
    {
        TurnamenMeja::query()
            ->where('id_kategori', $idKategori)
            ->where('is_aktif', true)
            ->update(['is_aktif' => false]);
    }

    protected function deleteAllMeja($idKategori): void
    {
        $ids = TurnamenMeja::query()->where('id_kategori', $idKategori)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        MahjongPoinEntry::query()->whereIn('id_meja', $ids)->update(['id_meja' => null]);
        TurnamenMejaSeat::query()->whereIn('id_meja', $ids)->delete();
        TurnamenMeja::query()->whereIn('id', $ids)->delete();
    }

    protected function syncPoinDidapatFromEntries(GrupMember $member): void
    {
        $member->loadMissing('grup');
        $teamBabak = (int) (optional($member->grup)->babak ?: 1);

        $sum = (int) MahjongPoinEntry::query()
            ->where('id_grup_member', $member->id)
            ->whereHas('meja', function ($query) use ($teamBabak) {
                $query->where('babak', $teamBabak);
            })
            ->sum('poin');

        $member->update(['poin_didapat' => $sum]);
    }

    protected function assertActiveMahjongTeamMember(GrupMember $member): void
    {
        $member->loadMissing('grup.turnamen');

        if (! $member->grup || ! $member->grup->turnamen || ! $member->grup->turnamen->isMahjongTeam()) {
            throw new RuntimeException('Input poin hanya untuk Mahjong Tim.');
        }

        if (! $member->grup->is_aktif) {
            throw new RuntimeException('Tim tidak aktif.');
        }
    }

    protected function activeMejaForMember(GrupMember $member): ?TurnamenMeja
    {
        $seat = TurnamenMejaSeat::query()
            ->where('id_grup_member', $member->id)
            ->whereHas('meja', function ($query) use ($member) {
                $query->where('is_aktif', true);

                if ($member->grup) {
                    $query->where('id_turnamen', $member->grup->id_turnamen);
                }
            })
            ->with('meja')
            ->first();

        return $seat ? $seat->meja : null;
    }

    protected function playersPerTeam(Turnamen $turnamen, $idKategori = null): int
    {
        return $this->resolveCompetitionKategori($turnamen, $idKategori)->mahjongPlayersPerTeam();
    }

    /**
     * @return Collection<int, TurnamenGrupPendaftaran>
     */
    protected function completeRegistrationTeams(Turnamen $turnamen, TurnamenKategori $kategori): Collection
    {
        return TurnamenGrupPendaftaran::query()
            ->forKategori($kategori->id)
            ->with(['members.peserta.pemain1'])
            ->orderBy('id')
            ->get()
            ->filter(fn (TurnamenGrupPendaftaran $group) => $group->isFullyApproved($turnamen))
            ->values();
    }

    /**
     * @param  array<string, true>  $usedNamesLower
     */
    protected function nextUnusedTeamName(array &$usedNamesLower, int &$sequence): string
    {
        do {
            $sequence++;
            $name = 'Tim '.$this->teamLabel($sequence);
            $lower = mb_strtolower($name);
        } while (isset($usedNamesLower[$lower]));

        $usedNamesLower[$lower] = true;

        return $name;
    }

    protected function approvedEntries(Turnamen $turnamen, $idKategori): Collection
    {
        return TurnamenPeserta::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('status', 'approved')
            ->with('pemain1')
            ->orderBy('id')
            ->get();
    }

    protected function teamLabel(int $number): string
    {
        return chr(ord('A') + $number - 1);
    }
}
