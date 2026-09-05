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

    /**
     * Klasemen / display order: total points → wins → akumulasi.
     * id_peserta is only a stable display order — not used for advance decisions.
     */
    public function compare(array $a, array $b): int
    {
        $score = $this->compareScores($a, $b);

        if ($score !== 0) {
            return $score;
        }

        $pesertaA = (int) ($a['id_peserta'] ?? 0);
        $pesertaB = (int) ($b['id_peserta'] ?? 0);

        return $pesertaA <=> $pesertaB;
    }

    /**
     * Score comparison used for advance cutlines (no last-round, no id).
     * Order: total points → wins → akumulasi.
     */
    public function compareScores(array $a, array $b): int
    {
        $totalA = (int) ($a['total_babak'] ?? $a['total_poin'] ?? 0);
        $totalB = (int) ($b['total_babak'] ?? $b['total_poin'] ?? 0);

        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }

        $winsA = (int) ($a['menang'] ?? 0);
        $winsB = (int) ($b['menang'] ?? 0);

        if ($winsA !== $winsB) {
            return $winsB <=> $winsA;
        }

        $akumA = (int) ($a['poin_akumulasi'] ?? 0);
        $akumB = (int) ($b['poin_akumulasi'] ?? 0);

        return $akumB <=> $akumA;
    }

    /**
     * Pick advance qualifiers by total → wins → akumulasi.
     * When a score-tied bubble spans the remaining slots, return needs_tiebreak
     * unless the admin supplies exact picks from that bubble.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  list<int>|null  $tiebreakPesertaIds
     * @return array{
     *     status: 'resolved'|'needs_tiebreak',
     *     qualifiers?: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     auto_qualified?: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     contested?: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     slots_remaining?: int
     * }
     */
    public function resolveAdvanceQualifiers(
        Collection $rows,
        int $jumlahLolos,
        ?array $tiebreakPesertaIds = null
    ): array {
        $sorted = $rows
            ->sort(function (array $a, array $b) {
                $score = $this->compareScores($a, $b);

                if ($score !== 0) {
                    return $score;
                }

                return ((int) ($a['id_peserta'] ?? 0)) <=> ((int) ($b['id_peserta'] ?? 0));
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
                if ($this->compareScores($sorted[$cursor], $anchor) !== 0) {
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

            $picks = collect($tiebreakPesertaIds ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($tiebreakPesertaIds === null) {
                return [
                    'status' => 'needs_tiebreak',
                    'auto_qualified' => $autoQualified->values(),
                    'contested' => $bubble->values(),
                    'slots_remaining' => $remaining,
                ];
            }

            if ($picks->count() !== $remaining) {
                throw new \RuntimeException(sprintf(
                    'Pilih tepat %d pemain dari daftar seri untuk lolos.',
                    $remaining
                ));
            }

            $contestedIds = $bubble->map(fn (array $row) => (int) ($row['id_peserta'] ?? 0))->all();

            foreach ($picks as $pesertaId) {
                if (! in_array($pesertaId, $contestedIds, true)) {
                    throw new \RuntimeException('Pemain yang dipilih harus berasal dari daftar seri.');
                }
            }

            $chosen = $bubble
                ->filter(fn (array $row) => $picks->contains((int) ($row['id_peserta'] ?? 0)))
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
