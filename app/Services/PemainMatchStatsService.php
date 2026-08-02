<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;

class PemainMatchStatsService
{
    /**
     * Career padel match stats from completed, scored pertandingan.
     * Excludes knockout byes and Mahjong (no match W/L).
     *
     * @param  Pemain|int  $pemain
     * @return array{played: int, won: int}
     */
    public function getCareerStats($pemain): array
    {
        $pemainId = $pemain instanceof Pemain ? (int) $pemain->id : (int) $pemain;
        $pesertaIds = TurnamenPeserta::query()
            ->involvingPemain($pemainId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $matches = $this->completedScoredMatchesFor($pemainId, $pesertaIds);

        $played = 0;
        $won = 0;

        foreach ($matches as $match) {
            $played++;

            if ($this->playerWon($match, $pemainId, $pesertaIds)) {
                $won++;
            }
        }

        return [
            'played' => $played,
            'won' => $won,
        ];
    }

    /**
     * @param  array<int, int>  $pesertaIds
     * @return Collection<int, Pertandingan>
     */
    protected function completedScoredMatchesFor(int $pemainId, array $pesertaIds): Collection
    {
        return Pertandingan::query()
            ->where('status', 'completed')
            ->whereHas('skor')
            ->where(function ($query) use ($pemainId, $pesertaIds) {
                $query->where('id_pemain1', $pemainId)
                    ->orWhere('id_pemain2', $pemainId)
                    ->orWhere('id_pemain1_partner', $pemainId)
                    ->orWhere('id_pemain2_partner', $pemainId);

                if ($pesertaIds !== []) {
                    $query->orWhereIn('id_peserta1', $pesertaIds)
                        ->orWhereIn('id_peserta2', $pesertaIds);
                }
            })
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @param  array<int, int>  $pesertaIds
     */
    protected function playerWon(Pertandingan $match, int $pemainId, array $pesertaIds): bool
    {
        if ($match->id_peserta_pemenang
            && in_array((int) $match->id_peserta_pemenang, $pesertaIds, true)
        ) {
            return true;
        }

        if (! $match->id_pemenang) {
            return false;
        }

        $playerSide = $match->resolveSideForPemain($pemainId);
        $winnerSide = $match->resolveSideForPemain((int) $match->id_pemenang);

        return $playerSide !== null
            && $winnerSide !== null
            && $playerSide === $winnerSide;
    }
}
