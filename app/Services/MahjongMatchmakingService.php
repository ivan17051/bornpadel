<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\MahjongPoinEntry;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use App\Models\TurnamenPeserta;
use App\Services\Concerns\ResolvesTurnamenKategori;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MahjongMatchmakingService
{
    use ResolvesTurnamenKategori;

    const PLAYERS_PER_GROUP = 4;

    protected $leaderboardService;
    protected $mahjongRanker;

    public function __construct(LeaderboardService $leaderboardService, MahjongStandingRanker $mahjongRanker)
    {
        $this->leaderboardService = $leaderboardService;
        $this->mahjongRanker = $mahjongRanker;
    }

    /**
     * Prior babak / ronde totals keyed by id_turnamen_peserta for Akumulasi breakdown UI.
     *
     * @return array<int, list<array{label: string, poin: int}>>
     */
    public function getPriorBabakBreakdown(Turnamen $turnamen, $idKategori = null): array
    {
        return $this->leaderboardService->getMahjongPriorBabakBreakdown($turnamen, $idKategori);
    }

    public function canGenerateGroups(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjong()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && ! $kategori->activeGrup()->exists();
    }

    public function canReshuffle(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjong()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && $kategori->activeGrup()->exists()
            && ! $kategori->mahjong_is_final;
    }

    public function canAdvanceRound(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjong()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && $kategori->activeGrup()->exists()
            && ! $kategori->mahjong_is_final;
    }

    public function canComplete(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjong()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && $kategori->mahjong_is_final
            && $kategori->activeGrup()->count() === 1
            && $kategori->activeGrup()->first()->members()->count() === self::PLAYERS_PER_GROUP;
    }

    public function canReset(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $turnamen->isMahjong()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && $kategori->grup()->exists();
    }

    public function resetGroupsAndMatches(Turnamen $turnamen, $idKategori = null): void
    {
        if (! $this->canReset($turnamen, $idKategori)) {
            throw new RuntimeException('Reset grup Mahjong hanya tersedia saat turnamen masih ongoing dan sudah ada grup.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        DB::transaction(function () use ($turnamen, $kategori) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);
            $lockedKategori = $this->resolveCompetitionKategori($locked, $kategori->id);

            if (! $this->canReset($locked, $lockedKategori->id)) {
                throw new RuntimeException('Reset grup Mahjong hanya tersedia saat turnamen masih ongoing dan sudah ada grup.');
            }

            $lockedKategori->grup()->delete();
            DB::table('turnamen_pemenang')->where('id_kategori', $lockedKategori->id)->delete();
            $this->updateCompetitionLifecycle($lockedKategori, [
                'mahjong_is_final' => false,
            ]);
        });
    }

    public function generateGroups(Turnamen $turnamen, string $mode = 'random', $idKategori = null): array
    {
        if (! $this->canGenerateGroups($turnamen, $idKategori)) {
            throw new RuntimeException('Grup Mahjong tidak dapat dibuat pada status turnamen ini.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $entries = $this->getApprovedEntries($turnamen, $kategori->id);
        $this->assertDivisibleByFour($entries->count());

        return $this->createGroupsFromEntries($turnamen, $kategori, $entries, 1, $mode);
    }

    public function reshuffleGroups(Turnamen $turnamen, string $mode = 'random', $idKategori = null): array
    {
        if (! $this->canReshuffle($turnamen, $idKategori)) {
            throw new RuntimeException('Grup Mahjong tidak dapat diacak ulang pada status turnamen ini.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return DB::transaction(function () use ($turnamen, $kategori, $mode) {
            $this->commitCurrentRoundPoints($turnamen, $kategori->id);

            $entries = $this->collectEntriesFromActiveGroups($turnamen, $kategori->id);
            $babak = (int) $kategori->activeGrup()->max('babak') ?: 1;

            $this->deactivateActiveGroups($turnamen, $kategori->id);

            return $this->createGroupsFromEntries($turnamen, $kategori, $entries, $babak, $mode, false);
        });
    }

    public function advanceRound(Turnamen $turnamen, int $jumlahLolos, $idKategori = null): array
    {
        if (! $this->canAdvanceRound($turnamen, $idKategori)) {
            throw new RuntimeException('Babak Mahjong tidak dapat dilanjutkan.');
        }

        if ($jumlahLolos < self::PLAYERS_PER_GROUP) {
            throw new RuntimeException('Minimal ' . self::PLAYERS_PER_GROUP . ' pemain untuk babak selanjutnya.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return DB::transaction(function () use ($turnamen, $kategori, $jumlahLolos) {
            $this->commitCurrentRoundPoints($turnamen, $kategori->id);

            $currentBabak = (int) $kategori->activeGrup()->max('babak') ?: 1;
            $qualifierRows = $this->leaderboardService
                ->buildMahjongBabakTable($turnamen, $currentBabak, $kategori->id)['rows']
                ->take($jumlahLolos)
                ->values();

            if ($qualifierRows->count() < self::PLAYERS_PER_GROUP) {
                throw new RuntimeException('Pemain lolos tidak cukup untuk membentuk grup.');
            }

            if ($qualifierRows->count() > self::PLAYERS_PER_GROUP
                && $qualifierRows->count() % self::PLAYERS_PER_GROUP !== 0) {
                throw new RuntimeException('Jumlah pemain lolos harus kelipatan ' . self::PLAYERS_PER_GROUP . '.');
            }

            $babak = $currentBabak + 1;
            $this->deactivateActiveGroups($turnamen, $kategori->id);

            $isFinal = $qualifierRows->count() === self::PLAYERS_PER_GROUP;

            $entries = $qualifierRows->map(function (array $row) {
                $peserta = TurnamenPeserta::find($row['id_peserta']);

                if ($peserta) {
                    $peserta->mahjong_carry_points = (int) ($row['total_babak'] ?? $row['total_poin'] ?? 0);
                }

                return $peserta;
            })->filter();

            $result = $this->createGroupsFromEntries($turnamen, $kategori, $entries, $babak, 'by_points', true);
            $this->updateCompetitionLifecycle($kategori, [
                'mahjong_is_final' => $isFinal,
            ]);
            $turnamen->update(['mahjong_is_final' => $isFinal]);

            $result['is_final'] = $isFinal;
            $result['babak'] = $babak;
            $result['qualifiers'] = $qualifierRows->count();

            return $result;
        });
    }

    public function updateMemberPoints(GrupMember $member, int $poinDidapat): GrupMember
    {
        // Legacy overwrite kept for rare callers; UI uses addMemberPointEntry.
        $this->assertActiveMahjongMember($member);
        $member->update(['poin_didapat' => $poinDidapat]);

        return $member->fresh(['poinEntries', 'grup.turnamen']);
    }

    public function addMemberPointEntry(GrupMember $member, int $poin): GrupMember
    {
        $this->assertActiveMahjongMember($member);

        MahjongPoinEntry::create([
            'id_grup_member' => $member->id,
            'poin' => $poin,
            'is_winner' => false,
        ]);

        return $this->syncPoinDidapatFromEntries($member);
    }

    /**
     * Add one poin entry for every member in the group (one mahjong hand).
     * Exactly one winner member must be provided.
     *
     * @param  array<int, array{id: int, poin: int}>  $scores
     * @return Collection<int, GrupMember>
     */
    public function addGroupPointEntries(Grup $grup, array $scores, int $winnerMemberId): Collection
    {
        return DB::transaction(function () use ($grup, $scores, $winnerMemberId) {
            $this->assertActiveMahjongGroup($grup);

            $grup->loadMissing('members');
            $membersById = $grup->members->keyBy('id');

            if ($membersById->count() !== self::PLAYERS_PER_GROUP) {
                throw new RuntimeException('Grup Mahjong harus berisi tepat 4 pemain.');
            }

            if (count($scores) !== self::PLAYERS_PER_GROUP) {
                throw new RuntimeException('Poin harus diisi untuk keempat pemain dalam grup.');
            }

            $scoreIds = collect($scores)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $memberIds = $membersById->keys()->map(fn ($id) => (int) $id)->sort()->values();

            if ($scoreIds->all() !== $memberIds->all()) {
                throw new RuntimeException('Daftar pemain tidak cocok dengan anggota grup.');
            }

            if (! $membersById->has($winnerMemberId)) {
                throw new RuntimeException('Pemenang harus salah satu anggota grup.');
            }

            foreach ($scores as $score) {
                $memberId = (int) $score['id'];
                MahjongPoinEntry::create([
                    'id_grup_member' => $memberId,
                    'poin' => (int) $score['poin'],
                    'is_winner' => $memberId === $winnerMemberId,
                ]);
            }

            return $membersById->values()->map(function (GrupMember $member) {
                return $this->syncPoinDidapatFromEntries($member);
            })->values();
        });
    }

    public function deleteMemberPointEntry(GrupMember $member, MahjongPoinEntry $entry): GrupMember
    {
        $this->assertActiveMahjongMember($member);

        if ((int) $entry->id_grup_member !== (int) $member->id) {
            throw new RuntimeException('Entri poin tidak cocok dengan anggota grup.');
        }

        $entry->delete();

        return $this->syncPoinDidapatFromEntries($member);
    }

    public function updateMemberPointEntry(GrupMember $member, MahjongPoinEntry $entry, int $poin): GrupMember
    {
        $this->assertActiveMahjongMember($member);

        if ((int) $entry->id_grup_member !== (int) $member->id) {
            throw new RuntimeException('Entri poin tidak cocok dengan anggota grup.');
        }

        $entry->update(['poin' => $poin]);

        return $this->syncPoinDidapatFromEntries($member);
    }

    public function syncPoinDidapatFromEntries(GrupMember $member): GrupMember
    {
        $sum = (int) $member->poinEntries()->sum('poin');
        $member->update(['poin_didapat' => $sum]);

        return $member->fresh(['poinEntries', 'grup.turnamen']);
    }

    protected function assertActiveMahjongMember(GrupMember $member): void
    {
        $member->loadMissing('grup.turnamen');

        if (! $member->grup) {
            throw new RuntimeException('Anggota grup tidak ditemukan.');
        }

        $this->assertActiveMahjongGroup($member->grup);
    }

    protected function assertActiveMahjongGroup(Grup $grup): void
    {
        $grup->loadMissing('turnamen');

        if (! $grup->turnamen || ! $grup->turnamen->isMahjong()) {
            throw new RuntimeException('Pembaruan poin hanya untuk turnamen Mahjong.');
        }

        if (! $grup->is_aktif) {
            throw new RuntimeException('Grup tidak aktif.');
        }
    }

    public function getApprovedEntries(Turnamen $turnamen, $idKategori = null): Collection
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return TurnamenPeserta::query()
            ->forKategori($kategori->id)
            ->approved()
            ->with('pemain1')
            ->orderBy('id')
            ->get();
    }

    public function getGlobalRankings(Turnamen $turnamen, $idKategori = null): Collection
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $babak = (int) $kategori->activeGrup()->max('babak');

        if ($babak > 0) {
            return $this->leaderboardService
                ->buildMahjongBabakTable($turnamen, $babak, $kategori->id)['rows']
                ->map(function (array $row) {
                    return [
                        'peserta' => TurnamenPeserta::find($row['id_peserta']),
                        'pemain' => Pemain::find($row['id_pemain']),
                        'member' => null,
                        'total_poin' => (int) ($row['total_babak'] ?? $row['total_poin'] ?? 0),
                    ];
                });
        }

        $rows = collect();

        foreach ($this->getActiveMembers($turnamen, $kategori->id) as $member) {
            $rows->push([
                'peserta' => $member->turnamenPeserta,
                'pemain' => $member->pemain,
                'member' => $member,
                'total_poin' => $member->total_poin,
                'poin_akumulasi' => (int) $member->poin_akumulasi,
                'poin_didapat' => (int) $member->poin_didapat,
                'id_peserta' => $member->id_turnamen_peserta,
                'id_pemain' => $member->id_pemain,
            ]);
        }

        return $this->mahjongRanker->rankRows($rows)->map(function (array $row) {
            return [
                'peserta' => $row['peserta'],
                'pemain' => $row['pemain'],
                'member' => $row['member'],
                'total_poin' => (int) ($row['total_poin'] ?? 0),
            ];
        });
    }

    public function getGroupStandingsPayload(Turnamen $turnamen, $idKategori = null): array
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $groups = $kategori->activeGrup()
            ->with(['members.pemain', 'members.turnamenPeserta'])
            ->orderBy('nama')
            ->get();

        return [
            'turnamen' => [
                'id' => $turnamen->id,
                'nama' => $turnamen->nama,
                'jenis' => $turnamen->jenis,
                'status' => $turnamen->status,
                'mahjong_is_final' => (bool) $kategori->mahjong_is_final,
            ],
            'kategori' => [
                'id' => $kategori->id,
                'nama' => $kategori->nama,
            ],
            'groups' => $groups->map(function (Grup $grup) {
                return [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'babak' => $grup->babak,
                    'members' => $grup->orderedStandings()->get()->map(function (GrupMember $member) {
                        return [
                            'id_pemain' => $member->id_pemain,
                            'id_peserta' => $member->id_turnamen_peserta,
                            'nama' => $member->display_name,
                            'poin_babak' => (int) $member->poin_didapat,
                            'poin_akumulasi' => (int) $member->poin_akumulasi,
                            'total_poin' => $member->total_poin,
                        ];
                    })->values(),
                ];
            })->values(),
            'global_rankings' => $this->getGlobalRankings($turnamen, $kategori->id)->map(function (array $row) {
                return [
                    'id_pemain' => optional($row['pemain'])->id,
                    'id_peserta' => optional($row['peserta'])->id,
                    'nama' => optional($row['pemain'])->nama,
                    'total_poin' => $row['total_poin'],
                ];
            })->values(),
        ];
    }

    public function resolveFinalPlacements(Turnamen $turnamen, $idKategori = null): array
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $finalGroup = $kategori->activeGrup()->with('members.pemain', 'members.turnamenPeserta')->first();

        if (! $finalGroup) {
            throw new RuntimeException('Grup final Mahjong tidak ditemukan.');
        }

        $ranked = $finalGroup->orderedStandings()->get();
        $placements = [];

        foreach ([1, 2, 3] as $index => $place) {
            $member = $ranked->get($index);

            if (! $member) {
                continue;
            }

            $placements[$place] = [
                'pemain_ids' => [$member->id_pemain],
                'peserta_id' => $member->id_turnamen_peserta,
                'total_poin' => $member->total_poin,
            ];
        }

        return $placements;
    }

    protected function createGroupsFromEntries(
        Turnamen $turnamen,
        TurnamenKategori $kategori,
        Collection $entries,
        int $babak,
        string $mode,
        bool $resetRoundPoints = true
    ): array {
        $ordered = $this->orderEntries($entries, $mode);
        $chunks = $ordered->chunk(self::PLAYERS_PER_GROUP)->values();
        $ronde = $this->nextMahjongRonde($kategori, $babak);
        $result = ['groups' => [], 'babak' => $babak, 'ronde' => $ronde, 'mode' => $mode];

        foreach ($chunks as $index => $groupEntries) {
            $grup = Grup::create([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $kategori->id,
                'nama' => 'Grup ' . $this->groupLabel($index + 1),
                'babak' => $babak,
                'ronde' => $ronde,
                'is_aktif' => true,
            ]);

            foreach ($groupEntries as $entry) {
                /** @var TurnamenPeserta $entry */
                $akumulasi = $resetRoundPoints ? 0 : (int) ($entry->mahjong_carry_points ?? 0);

                GrupMember::create([
                    'id_grup' => $grup->id,
                    'id_pemain' => $entry->id_pemain1,
                    'id_turnamen_peserta' => $entry->id,
                    'poin_didapat' => 0,
                    'poin_akumulasi' => $akumulasi,
                ]);
            }

            $result['groups'][] = [
                'id' => $grup->id,
                'nama' => $grup->nama,
                'pemain_count' => $groupEntries->count(),
            ];
        }

        return $result;
    }

    protected function collectEntriesFromActiveGroups(Turnamen $turnamen, $idKategori = null): Collection
    {
        $entries = collect();

        foreach ($this->getActiveMembers($turnamen, $idKategori) as $member) {
            if (! $member->turnamenPeserta) {
                continue;
            }

            $member->turnamenPeserta->mahjong_carry_points = $member->total_poin;
            $entries->push($member->turnamenPeserta);
        }

        $this->assertDivisibleByFour($entries->count());

        return $entries->unique('id')->values();
    }

    public function commitCurrentRoundPoints(Turnamen $turnamen, $idKategori = null): void
    {
        foreach ($this->getActiveMembers($turnamen, $idKategori) as $member) {
            $member->update([
                'poin_akumulasi' => $member->total_poin,
                'poin_didapat' => 0,
            ]);
        }
    }

    protected function deactivateActiveGroups(Turnamen $turnamen, $idKategori = null): void
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $kategori->activeGrup()->update(['is_aktif' => false]);
    }

    protected function getActiveMembers(Turnamen $turnamen, $idKategori = null): Collection
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return GrupMember::query()
            ->whereHas('grup', function ($query) use ($kategori) {
                $query->where('id_kategori', $kategori->id)->where('is_aktif', true);
            })
            ->with(['pemain', 'turnamenPeserta'])
            ->get();
    }

    protected function orderEntries(Collection $entries, string $mode): Collection
    {
        if ($mode === 'by_points') {
            return $entries->sortByDesc(function ($entry) {
                return $entry->mahjong_carry_points ?? 0;
            })->values();
        }

        if ($mode === 'by_rating') {
            return $entries->sortByDesc(function (TurnamenPeserta $entry) {
                return optional($entry->pemain1)->rating ?? 0;
            })->values();
        }

        return $entries->shuffle()->values();
    }

    protected function assertDivisibleByFour(int $count): void
    {
        if ($count < self::PLAYERS_PER_GROUP) {
            throw new RuntimeException('Minimal ' . self::PLAYERS_PER_GROUP . ' pemain approved diperlukan.');
        }

        if ($count % self::PLAYERS_PER_GROUP !== 0) {
            throw new RuntimeException('Jumlah pemain approved harus kelipatan ' . self::PLAYERS_PER_GROUP . '.');
        }
    }

    protected function groupLabel(int $index): string
    {
        return chr(64 + $index);
    }

    protected function nextMahjongRonde(TurnamenKategori $kategori, int $babak): int
    {
        $maxRonde = Grup::query()
            ->where('id_kategori', $kategori->id)
            ->where('babak', $babak)
            ->max('ronde');

        return $maxRonde ? ((int) $maxRonde + 1) : 1;
    }
}
