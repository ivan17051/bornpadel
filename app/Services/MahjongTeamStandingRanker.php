<?php

namespace App\Services;

use Illuminate\Support\Collection;

class MahjongTeamStandingRanker
{
    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  list<int>|null  $tiebreakTimIds
     * @return array{
     *     status: 'resolved'|'needs_tiebreak',
     *     qualifiers?: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     auto_qualified?: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     contested?: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     slots_remaining?: int
     * }
     */
    public function resolveAdvanceTeams(
        Collection $rows,
        int $jumlahLolos,
        ?array $tiebreakTimIds = null
    ): array {
        $sorted = $rows
            ->sort(function (array $a, array $b) {
                $score = ((int) ($b['total_poin'] ?? 0)) <=> ((int) ($a['total_poin'] ?? 0));

                if ($score !== 0) {
                    return $score;
                }

                return ((int) ($a['id_tim'] ?? 0)) <=> ((int) ($b['id_tim'] ?? 0));
            })
            ->values();

        if ($jumlahLolos <= 0) {
            return [
                'status' => 'resolved',
                'qualifiers' => collect(),
            ];
        }

        if ($sorted->count() <= $jumlahLolos) {
            return [
                'status' => 'resolved',
                'qualifiers' => $sorted->values(),
            ];
        }

        $autoQualified = collect();
        $remaining = $jumlahLolos;
        $index = 0;

        while ($index < $sorted->count() && $remaining > 0) {
            $anchor = $sorted[$index];
            $bubble = collect();

            for ($cursor = $index; $cursor < $sorted->count(); $cursor++) {
                if (((int) ($sorted[$cursor]['total_poin'] ?? 0)) !== ((int) ($anchor['total_poin'] ?? 0))) {
                    break;
                }

                $bubble->push($sorted[$cursor]);
            }

            $bubbleSize = $bubble->count();

            if ($bubbleSize <= $remaining) {
                $autoQualified = $autoQualified->concat($bubble);
                $remaining -= $bubbleSize;
                $index += $bubbleSize;

                continue;
            }

            $picks = collect($tiebreakTimIds ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($tiebreakTimIds === null) {
                return [
                    'status' => 'needs_tiebreak',
                    'auto_qualified' => $autoQualified->values(),
                    'contested' => $bubble->values(),
                    'slots_remaining' => $remaining,
                ];
            }

            if ($picks->count() !== $remaining) {
                throw new \RuntimeException(sprintf(
                    'Pilih tepat %d tim dari daftar seri untuk lolos.',
                    $remaining
                ));
            }

            $contestedIds = $bubble->map(fn (array $row) => (int) ($row['id_tim'] ?? 0))->all();

            foreach ($picks as $timId) {
                if (! in_array($timId, $contestedIds, true)) {
                    throw new \RuntimeException('Tim yang dipilih harus berasal dari daftar seri.');
                }
            }

            $chosen = $bubble
                ->filter(fn (array $row) => $picks->contains((int) ($row['id_tim'] ?? 0)))
                ->values();

            return [
                'status' => 'resolved',
                'qualifiers' => $autoQualified->concat($chosen)->values(),
            ];
        }

        return [
            'status' => 'resolved',
            'qualifiers' => $autoQualified->values(),
        ];
    }
}
