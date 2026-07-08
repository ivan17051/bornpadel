<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Single source of truth for Mahjong klasemen / qualifier ordering.
 */
class MahjongStandingRanker
{
    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function rankRows(Collection $rows): Collection
    {
        return $rows
            ->sort(function (array $a, array $b) {
                return $this->compare($a, $b);
            })
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    public function compare(array $a, array $b): int
    {
        $totalA = (int) ($a['total_babak'] ?? $a['total_poin'] ?? 0);
        $totalB = (int) ($b['total_babak'] ?? $b['total_poin'] ?? 0);

        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }

        $lastA = $this->lastRoundScore($a);
        $lastB = $this->lastRoundScore($b);

        if ($lastA !== $lastB) {
            return $lastB <=> $lastA;
        }

        $akumA = (int) ($a['poin_akumulasi'] ?? 0);
        $akumB = (int) ($b['poin_akumulasi'] ?? 0);

        if ($akumA !== $akumB) {
            return $akumB <=> $akumA;
        }

        $pesertaA = (int) ($a['id_peserta'] ?? 0);
        $pesertaB = (int) ($b['id_peserta'] ?? 0);

        return $pesertaA <=> $pesertaB;
    }

    protected function lastRoundScore(array $row): int
    {
        $scores = $row['round_scores'] ?? [];

        if (! is_array($scores) || $scores === []) {
            return (int) ($row['poin_didapat'] ?? 0);
        }

        return (int) end($scores);
    }
}
