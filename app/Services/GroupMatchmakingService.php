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
        return $turnamen->isDouble() ? 'pasangan' : 'pemain';
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

    public function resolveTournament(?int $id = null): ?Turnamen
    {
        return app(TournamentAccessService::class)->resolveTurnamen($id, $this);
    }

    public function listForFilter(): Collection
    {
        return app(TournamentAccessService::class)->listForFilter();
    }

    public function canCloseRegistration(Turnamen $turnamen): bool
    {
        return $turnamen->status === 'open';
    }

    public function closeRegistration(Turnamen $turnamen): Turnamen
    {
        if (! $this->canCloseRegistration($turnamen)) {
            throw new RuntimeException('Pendaftaran sudah ditutup atau turnamen belum dibuka.');
        }

        $turnamen->update(['status' => 'ongoing']);

        return $turnamen->fresh();
    }

    public function canGenerateRandomGroups(Turnamen $turnamen): bool
    {
        return $turnamen->status === 'ongoing'
            && ! $turnamen->grup()->exists();
    }

    public function getApprovedEntries(Turnamen $turnamen): Collection
    {
        $query = TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->with(['pemain1', 'pemain2']);

        if ($turnamen->isDouble()) {
            $query->completePairs();
        }

        return $query->orderBy('id')->get();
    }

    public function countApprovedPlayers(Turnamen $turnamen): int
    {
        return $this->getApprovedEntries($turnamen)->count();
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

        if ($turnamen->grup()->exists()) {
            throw new RuntimeException('Grup sudah dibuat untuk turnamen ini.');
        }

        if (! in_array($mode, ['random', 'by_rating'], true)) {
            throw new RuntimeException('Mode pembagian grup tidak valid.');
        }

        $entries = $this->getApprovedEntries($turnamen);
        $groupSizes = $this->calculateGroupSizes($entries->count(), $minPerGroup, $maxPerGroup);

        return DB::transaction(function () use ($turnamen, $entries, $groupSizes, $mode) {
            $chunks = $this->distributeEntriesIntoGroups($entries, $groupSizes, $mode);
            $result = ['groups' => [], 'matches' => 0, 'mode' => $mode, 'group_sizes' => $groupSizes];

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

                $matchCount = $this->generateRoundRobinMatches($turnamen, $grup, $groupEntries);
                $result['matches'] += $matchCount;
                $result['groups'][] = [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'pemain_count' => $groupEntries->count(),
                    'matches' => $matchCount,
                ];
            }

            return $result;
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
