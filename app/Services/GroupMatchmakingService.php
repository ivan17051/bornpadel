<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GroupMatchmakingService
{
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

    public function canCloseRegistration(Turnamen $turnamen): bool
    {
        if ($turnamen->status !== 'open') {
            return false;
        }

        if ($turnamen->playsAsPairs()) {
            $summary = $this->pairingService->getSummary($turnamen);

            return ! $summary['odd_player_warning'];
        }

        return true;
    }

    /**
     * @return array{turnamen: Turnamen, pairing: array<string, mixed>|null}
     */
    public function closeRegistration(Turnamen $turnamen): array
    {
        if (! $turnamen->isRegistrationOpen()) {
            throw new RuntimeException('Pendaftaran sudah ditutup atau turnamen belum dibuka.');
        }

        $pairingResult = DB::transaction(function () use ($turnamen) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);
            $result = null;

            if ($locked->randomizesPartners()) {
                $result = $this->pairingService->pairApprovedPlayers($locked);
                $locked->update([
                    'status' => 'ongoing',
                    'registration_paired_at' => now(),
                    'group_matches_generated_at' => null,
                ]);
            } elseif ($locked->requiresPairRegistration()) {
                $this->pairingService->assertCanCloseWithoutRandomPairing($locked);
                $locked->update([
                    'status' => 'ongoing',
                    'registration_paired_at' => now(),
                    'group_matches_generated_at' => null,
                ]);
                $result = [
                    'pairs_created' => 0,
                    'pairs' => [],
                ];
            } else {
                $locked->update([
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

    public function canGenerateRandomGroups(Turnamen $turnamen): bool
    {
        if ($turnamen->isMahjong()) {
            return app(MahjongMatchmakingService::class)->canGenerateGroups($turnamen);
        }

        if ($turnamen->isFriendly()) {
            $friendly = app(FriendlyMatchmakingService::class);

            return $friendly->canGenerateGroups($turnamen)
                || $friendly->canRandomizeUnassigned($turnamen);
        }

        return $turnamen->status === 'ongoing'
            && ! $this->hasGeneratedGroupMatches($turnamen);
    }

    public function canEditGroups(Turnamen $turnamen): bool
    {
        if ($turnamen->isFriendly()) {
            return app(FriendlyMatchmakingService::class)->canEditGroups($turnamen);
        }

        return ! $turnamen->isMahjong()
            && $turnamen->status === 'ongoing'
            && $turnamen->grup()->exists()
            && ! $this->hasGeneratedGroupMatches($turnamen);
    }

    public function canGenerateGroupMatches(Turnamen $turnamen): bool
    {
        if ($turnamen->isFriendly() || $turnamen->isMahjong()) {
            return false;
        }

        return $this->canEditGroups($turnamen);
    }

    public function canResetGroupsAndMatches(Turnamen $turnamen): bool
    {
        if ($turnamen->isFriendly()) {
            return app(FriendlyMatchmakingService::class)->canReset($turnamen);
        }

        if ($turnamen->isMahjong()) {
            return app(MahjongMatchmakingService::class)->canReset($turnamen);
        }

        if ($turnamen->status !== 'ongoing') {
            return false;
        }

        $hasCompetitionData = $turnamen->grup()->exists() || $turnamen->pertandingan()->exists();

        return $hasCompetitionData
            && ! $turnamen->pertandingan()->where('status', 'completed')->exists()
            && ! $turnamen->pertandingan()->whereHas('skor')->exists();
    }

    protected function hasGeneratedGroupMatches(Turnamen $turnamen): bool
    {
        return $turnamen->group_matches_generated_at !== null
            || $turnamen->pertandingan()
                ->whereNotNull('id_grup')
                ->where('nama_ronde', 'Fase Grup')
                ->exists();
    }

    public function getApprovedEntries(Turnamen $turnamen): Collection
    {
        $query = TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
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

    public function countApprovedPlayers(Turnamen $turnamen): int
    {
        if ($turnamen->playsAsPairs() && $turnamen->isRegistrationOpen()) {
            return $this->pairingService->countApprovedIndividuals($turnamen);
        }

        return $this->getApprovedEntries($turnamen)->count();
    }

    public function countApprovedPairs(Turnamen $turnamen): int
    {
        if (! $turnamen->playsAsPairs()) {
            return $this->getApprovedEntries($turnamen)->count();
        }

        if ($turnamen->isRegistrationOpen()) {
            if ($turnamen->randomizesPartners()) {
                return intdiv($this->pairingService->countApprovedSolos($turnamen), 2)
                    + $this->pairingService->countApprovedCompletePairs($turnamen);
            }

            return $this->pairingService->countApprovedCompletePairs($turnamen);
        }

        return $this->getApprovedEntries($turnamen)->count();
    }

    public function getDoublePairingSummary(Turnamen $turnamen): ?array
    {
        if (! $turnamen->playsAsPairs()) {
            return null;
        }

        return $this->pairingService->getSummary($turnamen);
    }

    /** @deprecated Use getApprovedEntries() */
    public function getApprovedPlayers(Turnamen $turnamen): Collection
    {
        return $this->getApprovedEntries($turnamen);
    }

    public function generateRandomGroups(
        Turnamen $turnamen,
        int $minPerGroup,
        int $maxPerGroup,
        string $mode = 'random'
    ): array {
        if ($turnamen->status === 'open') {
            throw new RuntimeException('Pendaftaran masih dibuka. Tutup pendaftaran terlebih dahulu.');
        }

        if ($turnamen->status === 'draft' || $turnamen->status === 'completed') {
            throw new RuntimeException('Turnamen tidak dalam status yang valid untuk pembagian grup.');
        }

        if ($turnamen->isMahjong()) {
            throw new RuntimeException('Gunakan fitur Mahjong untuk membuat grup turnamen ini.');
        }

        if ($turnamen->isFriendly()) {
            return app(FriendlyMatchmakingService::class)->generateGroups($turnamen, $mode);
        }

        if ($this->hasGeneratedGroupMatches($turnamen)) {
            throw new RuntimeException('Matchmaking sudah dibuat. Grup tidak dapat diacak ulang.');
        }

        if (! in_array($mode, ['random', 'by_rating'], true)) {
            throw new RuntimeException('Mode pembagian grup tidak valid.');
        }

        $entries = $this->getApprovedEntries($turnamen);
        $groupSizes = $this->calculateGroupSizes($entries->count(), $minPerGroup, $maxPerGroup);

        return DB::transaction(function () use ($turnamen, $entries, $groupSizes, $mode) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);

            if ($this->hasGeneratedGroupMatches($locked)) {
                throw new RuntimeException('Matchmaking sudah dibuat. Grup tidak dapat diacak ulang.');
            }

            if ($locked->grup()->exists()) {
                $locked->grup()->delete();
            }

            $chunks = $this->distributeEntriesIntoGroups($entries, $groupSizes, $mode);
            $result = ['groups' => [], 'mode' => $mode, 'group_sizes' => $groupSizes];

            foreach ($chunks as $index => $groupEntries) {
                $grup = Grup::create([
                    'id_turnamen' => $turnamen->id,
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

    public function swapGroupMembers(Turnamen $turnamen, GrupMember $first, GrupMember $second): void
    {
        if (! $this->canEditGroups($turnamen)) {
            throw new RuntimeException('Grup hanya dapat diubah sebelum matchmaking dibuat.');
        }

        if ($first->id === $second->id) {
            throw new RuntimeException('Pilih dua peserta yang berbeda.');
        }

        $first->loadMissing('grup');
        $second->loadMissing('grup');

        if (! $first->grup || ! $second->grup
            || (int) $first->grup->id_turnamen !== (int) $turnamen->id
            || (int) $second->grup->id_turnamen !== (int) $turnamen->id) {
            throw new RuntimeException('Peserta grup tidak berasal dari turnamen yang dipilih.');
        }

        if ((int) $first->id_grup === (int) $second->id_grup) {
            throw new RuntimeException('Peserta sudah berada di grup yang sama.');
        }

        DB::transaction(function () use ($turnamen, $first, $second) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);

            if (! $this->canEditGroups($locked)) {
                throw new RuntimeException('Grup hanya dapat diubah sebelum matchmaking dibuat.');
            }

            $firstGroupId = $first->id_grup;
            $secondGroupId = $second->id_grup;

            GrupMember::query()->whereKey($first->id)->update(['id_grup' => $secondGroupId]);
            GrupMember::query()->whereKey($second->id)->update(['id_grup' => $firstGroupId]);
        });
    }

    public function generateGroupMatches(Turnamen $turnamen): array
    {
        if (! $this->canGenerateGroupMatches($turnamen)) {
            throw new RuntimeException('Grup belum siap atau matchmaking sudah dibuat.');
        }

        return DB::transaction(function () use ($turnamen) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);

            if ($this->hasGeneratedGroupMatches($locked)) {
                throw new RuntimeException('Matchmaking fase grup sudah dibuat.');
            }

            $groups = Grup::query()
                ->where('id_turnamen', $locked->id)
                ->with(['members.turnamenPeserta'])
                ->orderBy('id')
                ->get();

            $assignedIds = $groups->flatMap(function (Grup $group) {
                return $group->members->pluck('id_turnamen_peserta');
            })->filter()->map(fn ($id) => (int) $id)->values();
            $approvedIds = $this->getApprovedEntries($locked)->pluck('id')->map(fn ($id) => (int) $id)->values();

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
                $matchCount += $this->generateRoundRobinMatches($locked, $group, $entries);
            }

            $locked->update(['group_matches_generated_at' => now()]);

            return ['groups' => $groups->count(), 'matches' => $matchCount];
        });
    }

    public function resetGroupsAndMatches(Turnamen $turnamen): void
    {
        if (! $this->canResetGroupsAndMatches($turnamen)) {
            throw new RuntimeException('Reset hanya tersedia sebelum skor pertandingan dicatat.');
        }

        if ($turnamen->isFriendly()) {
            app(FriendlyMatchmakingService::class)->resetGroupsAndMatches($turnamen);

            return;
        }

        if ($turnamen->isMahjong()) {
            app(MahjongMatchmakingService::class)->resetGroupsAndMatches($turnamen);

            return;
        }

        DB::transaction(function () use ($turnamen) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);

            if ($locked->pertandingan()->where('status', 'completed')->exists()
                || $locked->pertandingan()->whereHas('skor')->exists()) {
                throw new RuntimeException('Turnamen tidak dapat direset karena skor sudah dicatat.');
            }

            $locked->pertandingan()->update([
                'id_next_pertandingan' => null,
                'id_next_pertandingan_kalah' => null,
            ]);
            $locked->pertandingan()->delete();
            $locked->grup()->delete();
            DB::table('turnamen_pemenang')->where('id_turnamen', $locked->id)->delete();
            $locked->update([
                'status' => 'ongoing',
                'mahjong_is_final' => false,
                'group_matches_generated_at' => null,
            ]);
        });
    }

    protected function generateRoundRobinMatches(Turnamen $turnamen, Grup $grup, Collection $entries): int
    {
        $count = 0;
        $items = $entries->values();

        for ($i = 0; $i < $items->count(); $i++) {
            for ($j = $i + 1; $j < $items->count(); $j++) {
                /** @var TurnamenPeserta $side1 */
                $side1 = $items[$i];
                /** @var TurnamenPeserta $side2 */
                $side2 = $items[$j];

                Pertandingan::create([
                    'id_turnamen' => $turnamen->id,
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
