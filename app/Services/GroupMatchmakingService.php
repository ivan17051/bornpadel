<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\Concerns\ResolvesTurnamenKategori;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GroupMatchmakingService
{
    use ResolvesTurnamenKategori;

    const DEFAULT_MIN_PER_GROUP = 3;
    const DEFAULT_MAX_PER_GROUP = 4;

    protected $pairingService;

    public function __construct(DoublePairingService $pairingService)
    {
        $this->pairingService = $pairingService;
    }

    public function getDefaultMinPerGroup(): int
    {
        return self::DEFAULT_MIN_PER_GROUP;
    }

    public function getDefaultMaxPerGroup(): int
    {
        return self::DEFAULT_MAX_PER_GROUP;
    }

    public function unitLabel(Turnamen $turnamen): string
    {
        if ($turnamen->playsAsPairs()) {
            return 'pasangan';
        }

        return 'pemain';
    }

    public function previewGroupSplit(int $totalPlayers, int $minPerGroup, int $maxPerGroup): ?array
    {
        if ($totalPlayers < $minPerGroup) {
            return null;
        }

        try {
            $sizes = $this->calculateGroupSizes($totalPlayers, $minPerGroup, $maxPerGroup);

            return [
                'group_count' => count($sizes),
                'sizes' => $sizes,
                'label' => implode(' + ', $sizes),
            ];
        } catch (RuntimeException $e) {
            return null;
        }
    }

    public function calculateGroupSizes(int $totalPlayers, int $minPerGroup, int $maxPerGroup): array
    {
        if ($minPerGroup > $maxPerGroup) {
            throw new RuntimeException('Minimum per grup tidak boleh lebih besar dari maksimum.');
        }

        if ($totalPlayers < $minPerGroup) {
            throw new RuntimeException("Minimal {$minPerGroup} peserta approved diperlukan.");
        }

        $minGroups = (int) ceil($totalPlayers / $maxPerGroup);
        $maxGroups = (int) floor($totalPlayers / $minPerGroup);

        if ($minGroups > $maxGroups) {
            throw new RuntimeException('Tidak dapat membagi peserta secara merata dengan batas min/max grup ini.');
        }

        for ($groupCount = $minGroups; $groupCount <= $maxGroups; $groupCount++) {
            $base = intdiv($totalPlayers, $groupCount);
            $remainder = $totalPlayers % $groupCount;
            $sizes = [];

            for ($i = 0; $i < $groupCount; $i++) {
                $sizes[] = $base + ($i < $remainder ? 1 : 0);
            }

            if (min($sizes) >= $minPerGroup && max($sizes) <= $maxPerGroup) {
                return $sizes;
            }
        }

        throw new RuntimeException('Tidak dapat membagi peserta secara merata dengan batas min/max grup ini.');
    }

    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::whereIn('status', ['open', 'ongoing'])
            ->latest('doc')
            ->first();
    }

    public function resolveTournament(?int $id = null, bool $fallbackToActive = true): ?Turnamen
    {
        return app(TournamentAccessService::class)->resolveTurnamen($id, $this, $fallbackToActive);
    }

    public function listForFilter(): Collection
    {
        return app(TournamentAccessService::class)->listForFilter();
    }

    public function canCloseRegistration(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if ($kategori->status !== 'open') {
            return false;
        }

        if ($turnamen->playsAsPairs()) {
            $summary = $this->pairingService->getSummary($turnamen, $kategori->id);

            return ! $summary['odd_player_warning'];
        }

        return true;
    }

    public function closeRegistration(Turnamen $turnamen, $idKategori = null): array
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if (! $kategori->isRegistrationOpen()) {
            throw new RuntimeException('Pendaftaran sudah ditutup atau turnamen belum dibuka.');
        }

        $pairingResult = DB::transaction(function () use ($turnamen, $idKategori) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);
            $kategori = $this->resolveCompetitionKategori($locked, $idKategori);

            if (! $kategori->isRegistrationOpen()) {
                throw new RuntimeException('Pendaftaran sudah ditutup atau turnamen belum dibuka.');
            }

            $result = null;

            if ($locked->randomizesPartners()) {
                $result = $this->pairingService->pairApprovedPlayers($locked, $kategori->id);
                $this->updateCompetitionLifecycle($kategori, [
                    'status' => 'ongoing',
                    'registration_paired_at' => now(),
                    'group_matches_generated_at' => null,
                ]);
            } elseif ($locked->requiresPairRegistration()) {
                $this->pairingService->assertCanCloseWithoutRandomPairing($locked, $kategori->id);
                $this->updateCompetitionLifecycle($kategori, [
                    'status' => 'ongoing',
                    'registration_paired_at' => now(),
                    'group_matches_generated_at' => null,
                ]);
                $result = [
                    'pairs_created' => 0,
                    'pairs' => [],
                ];
            } else {
                $this->updateCompetitionLifecycle($kategori, [
                    'status' => 'ongoing',
                    'group_matches_generated_at' => null,
                ]);
            }

            return $result;
        });

        return [
            'turnamen' => $turnamen->fresh(),
            'pairing' => $pairingResult,
        ];
    }

    public function canGenerateRandomGroups(Turnamen $turnamen, $idKategori = null): bool
    {
        if ($turnamen->isMahjong()) {
            return app(MahjongMatchmakingService::class)->canGenerateGroups($turnamen, $idKategori);
        }

        if ($turnamen->isFriendly()) {
            $friendly = app(FriendlyMatchmakingService::class);

            return $friendly->canGenerateGroups($turnamen, $idKategori)
                || $friendly->canRandomizeUnassigned($turnamen, $idKategori);
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $kategori->status === 'ongoing'
            && ! $this->hasGeneratedGroupMatches($turnamen, $kategori->id);
    }

    public function canEditGroups(Turnamen $turnamen, $idKategori = null): bool
    {
        if ($turnamen->isFriendly()) {
            return app(FriendlyMatchmakingService::class)->canEditGroups($turnamen, $idKategori);
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return ! $turnamen->isMahjong()
            && $this->isCompetitionOngoing($turnamen, $kategori->id)
            && $kategori->grup()->exists()
            && ! $this->hasGeneratedGroupMatches($turnamen, $kategori->id);
    }

    public function canGenerateGroupMatches(Turnamen $turnamen, $idKategori = null): bool
    {
        if ($turnamen->isFriendly() || $turnamen->isMahjong()) {
            return false;
        }

        return $this->canEditGroups($turnamen, $idKategori);
    }

    public function canResetGroupsAndMatches(Turnamen $turnamen, $idKategori = null): bool
    {
        if ($turnamen->isFriendly()) {
            return app(FriendlyMatchmakingService::class)->canReset($turnamen, $idKategori);
        }

        if ($turnamen->isMahjong()) {
            return app(MahjongMatchmakingService::class)->canReset($turnamen, $idKategori);
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if (! $this->isCompetitionOngoing($turnamen, $kategori->id)) {
            return false;
        }

        $hasCompetitionData = $kategori->grup()->exists() || $kategori->pertandingan()->exists();

        return $hasCompetitionData
            && ! $kategori->pertandingan()->where('status', 'completed')->exists()
            && ! $kategori->pertandingan()->whereHas('skor')->exists();
    }

    protected function hasGeneratedGroupMatches(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return $this->kategoriHasGeneratedGroupMatches($kategori);
    }

    public function getApprovedEntries(Turnamen $turnamen, $idKategori = null): Collection
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        $query = TurnamenPeserta::query()
            ->forKategori($kategori->id)
            ->approved()
            ->with(['pemain1', 'pasanganAsPeserta1.peserta2.pemain1']);

        if ($turnamen->playsAsPairs()) {
            $query->completePairs()
                ->whereHas('pasanganAsPeserta1.peserta2', function ($partner) {
                    $partner->approved();
                });
        }

        return $query->orderBy('id')->get();
    }

    public function countApprovedPlayers(Turnamen $turnamen, $idKategori = null): int
    {
        if ($turnamen->playsAsPairs() && $turnamen->isRegistrationOpen()) {
            return $this->pairingService->countApprovedIndividuals($turnamen, $idKategori);
        }

        return $this->getApprovedEntries($turnamen, $idKategori)->count();
    }

    public function countApprovedPairs(Turnamen $turnamen, $idKategori = null): int
    {
        if (! $turnamen->playsAsPairs()) {
            return $this->getApprovedEntries($turnamen, $idKategori)->count();
        }

        if ($turnamen->isRegistrationOpen()) {
            if ($turnamen->randomizesPartners()) {
                return intdiv($this->pairingService->countApprovedSolos($turnamen, $idKategori), 2)
                    + $this->pairingService->countApprovedCompletePairs($turnamen, $idKategori);
            }

            return $this->pairingService->countApprovedCompletePairs($turnamen, $idKategori);
        }

        return $this->getApprovedEntries($turnamen, $idKategori)->count();
    }

    public function getDoublePairingSummary(Turnamen $turnamen, $idKategori = null): ?array
    {
        if (! $turnamen->playsAsPairs()) {
            return null;
        }

        return $this->pairingService->getSummary($turnamen, $idKategori);
    }

    public function getApprovedPlayers(Turnamen $turnamen, $idKategori = null): Collection
    {
        return $this->getApprovedEntries($turnamen, $idKategori);
    }

    public function generateRandomGroups(
        Turnamen $turnamen,
        int $minPerGroup,
        int $maxPerGroup,
        string $mode = 'random',
        $idKategori = null
    ): array {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if ($kategori->status === 'open') {
            throw new RuntimeException('Pendaftaran masih dibuka. Tutup pendaftaran terlebih dahulu.');
        }

        if (in_array($kategori->status, ['draft', 'completed'], true)) {
            throw new RuntimeException('Turnamen tidak dalam status yang valid untuk pembagian grup.');
        }

        if ($turnamen->isMahjong()) {
            throw new RuntimeException('Gunakan fitur Mahjong untuk membuat grup turnamen ini.');
        }

        if ($turnamen->isFriendly()) {
            return app(FriendlyMatchmakingService::class)->generateGroups($turnamen, $mode, $kategori->id);
        }

        if ($this->hasGeneratedGroupMatches($turnamen, $kategori->id)) {
            throw new RuntimeException('Matchmaking sudah dibuat. Grup tidak dapat diacak ulang.');
        }

        if (! in_array($mode, ['random', 'by_rating'], true)) {
            throw new RuntimeException('Mode pembagian grup tidak valid.');
        }

        $entries = $this->getApprovedEntries($turnamen, $kategori->id);
        $groupSizes = $this->calculateGroupSizes($entries->count(), $minPerGroup, $maxPerGroup);

        return DB::transaction(function () use ($turnamen, $kategori, $entries, $groupSizes, $mode) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);
            $lockedKategori = $this->resolveCompetitionKategori($locked, $kategori->id);

            if ($this->kategoriHasGeneratedGroupMatches($lockedKategori)) {
                throw new RuntimeException('Matchmaking sudah dibuat. Grup tidak dapat diacak ulang.');
            }

            if ($lockedKategori->grup()->exists()) {
                $lockedKategori->grup()->delete();
            }

            $chunks = $this->distributeEntriesIntoGroups($entries, $groupSizes, $mode);
            $result = ['groups' => [], 'mode' => $mode, 'group_sizes' => $groupSizes];

            foreach ($chunks as $index => $groupEntries) {
                $grup = Grup::create([
                    'id_turnamen' => $turnamen->id,
                    'id_kategori' => $lockedKategori->id,
                    'nama' => 'Grup ' . $this->groupLabel($index + 1),
                ]);

                foreach ($groupEntries as $entry) {
                    GrupMember::create([
                        'id_grup' => $grup->id,
                        'id_pemain' => $entry->representative_pemain_id,
                        'id_turnamen_peserta' => $entry->id,
                    ]);
                }

                $result['groups'][] = [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'pemain_count' => $groupEntries->count(),
                ];
            }

            return $result;
        });
    }

    public function swapGroupMembers(Turnamen $turnamen, GrupMember $first, GrupMember $second, $idKategori = null): void
    {
        if (! $this->canEditGroups($turnamen, $idKategori)) {
            throw new RuntimeException('Grup hanya dapat diubah sebelum matchmaking dibuat.');
        }

        if ($first->id === $second->id) {
            throw new RuntimeException('Pilih dua peserta yang berbeda.');
        }

        $first->loadMissing('grup');
        $second->loadMissing('grup');
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if (! $first->grup || ! $second->grup
            || (int) $first->grup->id_kategori !== (int) $kategori->id
            || (int) $second->grup->id_kategori !== (int) $kategori->id) {
            throw new RuntimeException('Peserta grup tidak berasal dari kategori yang dipilih.');
        }

        if ((int) $first->id_grup === (int) $second->id_grup) {
            throw new RuntimeException('Peserta sudah berada di grup yang sama.');
        }

        DB::transaction(function () use ($turnamen, $first, $second, $kategori) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);

            if (! $this->canEditGroups($locked, $kategori->id)) {
                throw new RuntimeException('Grup hanya dapat diubah sebelum matchmaking dibuat.');
            }

            $firstGroupId = $first->id_grup;
            $secondGroupId = $second->id_grup;

            GrupMember::query()->whereKey($first->id)->update(['id_grup' => $secondGroupId]);
            GrupMember::query()->whereKey($second->id)->update(['id_grup' => $firstGroupId]);
        });
    }

    public function generateGroupMatches(Turnamen $turnamen, $idKategori = null): array
    {
        if (! $this->canGenerateGroupMatches($turnamen, $idKategori)) {
            throw new RuntimeException('Grup belum siap atau matchmaking sudah dibuat.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return DB::transaction(function () use ($turnamen, $kategori) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);
            $lockedKategori = $this->resolveCompetitionKategori($locked, $kategori->id);

            if ($this->kategoriHasGeneratedGroupMatches($lockedKategori)) {
                throw new RuntimeException('Matchmaking fase grup sudah dibuat.');
            }

            $groups = Grup::query()
                ->where('id_kategori', $lockedKategori->id)
                ->with(['members.turnamenPeserta'])
                ->orderBy('id')
                ->get();

            $assignedIds = $groups->flatMap(function (Grup $group) {
                return $group->members->pluck('id_turnamen_peserta');
            })->filter()->map(function ($id) {
                return (int) $id;
            })->values();
            $approvedIds = $this->getApprovedEntries($locked, $lockedKategori->id)->pluck('id')->map(function ($id) {
                return (int) $id;
            })->values();

            if ($assignedIds->count() !== $assignedIds->unique()->count()
                || $assignedIds->sort()->values()->all() !== $approvedIds->sort()->values()->all()) {
                throw new RuntimeException('Susunan grup tidak valid. Setiap peserta approved harus berada tepat di satu grup.');
            }

            $matchCount = 0;

            foreach ($groups as $group) {
                if ($group->members->count() < 2) {
                    throw new RuntimeException("{$group->nama} harus memiliki minimal 2 peserta.");
                }

                $entries = $group->members->map(function (GrupMember $member) {
                    if (! $member->turnamenPeserta) {
                        throw new RuntimeException('Data peserta grup tidak lengkap.');
                    }

                    return $member->turnamenPeserta;
                });
                $matchCount += $this->generateRoundRobinMatches($locked, $lockedKategori, $group, $entries);
            }

            $this->updateCompetitionLifecycle($lockedKategori, [
                'group_matches_generated_at' => now(),
            ]);

            return ['groups' => $groups->count(), 'matches' => $matchCount];
        });
    }

    public function resetGroupsAndMatches(Turnamen $turnamen, $idKategori = null): void
    {
        if (! $this->canResetGroupsAndMatches($turnamen, $idKategori)) {
            throw new RuntimeException('Reset hanya tersedia sebelum skor pertandingan dicatat.');
        }

        if ($turnamen->isFriendly()) {
            app(FriendlyMatchmakingService::class)->resetGroupsAndMatches($turnamen, $idKategori);

            return;
        }

        if ($turnamen->isMahjong()) {
            app(MahjongMatchmakingService::class)->resetGroupsAndMatches($turnamen, $idKategori);

            return;
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        DB::transaction(function () use ($turnamen, $kategori) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);
            $lockedKategori = $this->resolveCompetitionKategori($locked, $kategori->id);

            if ($lockedKategori->pertandingan()->where('status', 'completed')->exists()
                || $lockedKategori->pertandingan()->whereHas('skor')->exists()) {
                throw new RuntimeException('Turnamen tidak dapat direset karena skor sudah dicatat.');
            }

            $lockedKategori->pertandingan()->update([
                'id_next_pertandingan' => null,
                'id_next_pertandingan_kalah' => null,
            ]);
            $lockedKategori->pertandingan()->delete();
            $lockedKategori->grup()->delete();
            DB::table('turnamen_pemenang')->where('id_kategori', $lockedKategori->id)->delete();
            $this->updateCompetitionLifecycle($lockedKategori, [
                'status' => 'ongoing',
                'mahjong_is_final' => false,
                'group_matches_generated_at' => null,
            ]);
            $locked->update([
                'status' => 'ongoing',
                'mahjong_is_final' => false,
                'group_matches_generated_at' => null,
            ]);
        });
    }

    protected function generateRoundRobinMatches($turnamen, $kategori, Grup $grup, Collection $entries): int
    {
        $count = 0;
        $items = $entries->values();

        for ($i = 0; $i < $items->count(); $i++) {
            for ($j = $i + 1; $j < $items->count(); $j++) {
                $side1 = $items[$i];
                $side2 = $items[$j];

                Pertandingan::create([
                    'id_turnamen' => $turnamen->id,
                    'id_kategori' => $kategori->id,
                    'id_grup' => $grup->id,
                    'nama_ronde' => 'Fase Grup',
                    'id_pemain1' => $side1->representative_pemain_id,
                    'id_pemain2' => $side2->representative_pemain_id,
                    'id_peserta1' => $side1->id,
                    'id_peserta2' => $side2->id,
                    'status' => 'scheduled',
                ]);
                $count++;
            }
        }

        return $count;
    }

    protected function distributeEntriesIntoGroups(Collection $entries, array $groupSizes, string $mode): array
    {
        if ($mode === 'by_rating') {
            $ordered = $entries->sortByDesc(function (TurnamenPeserta $entry) {
                return $entry->average_rating;
            })->values();
        } else {
            $ordered = $entries->shuffle()->values();
        }

        $groups = [];
        $offset = 0;

        foreach ($groupSizes as $size) {
            $groups[] = $ordered->slice($offset, $size)->values();
            $offset += $size;
        }

        return $groups;
    }

    protected function groupLabel(int $index): string
    {
        return chr(64 + $index);
    }
}
