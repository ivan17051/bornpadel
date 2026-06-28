<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\TurnamenPeserta;

class PointRewardService
{
    public function awardMatchWin(Pertandingan $pertandingan): void
    {
        $points = (int) config('tournament.points.match_win', 10);

        if ($points <= 0) {
            return;
        }

        $pemainIds = $this->resolveWinnerPemainIds($pertandingan);

        if ($pemainIds === []) {
            return;
        }

        Pemain::whereIn('id', $pemainIds)->increment('total_poin', $points);
    }

    /**
     * @param  array<int, array{pemain_ids: int[], points: int}>  $awards
     */
    public function awardPlacementPoints(array $awards): void
    {
        foreach ($awards as $award) {
            if ($award['points'] <= 0 || $award['pemain_ids'] === []) {
                continue;
            }

            Pemain::whereIn('id', $award['pemain_ids'])->increment('total_poin', $award['points']);
        }
    }

    public function resolveWinnerPemainIds(Pertandingan $pertandingan): array
    {
        if ($pertandingan->id_peserta_pemenang) {
            $peserta = TurnamenPeserta::find($pertandingan->id_peserta_pemenang);

            return $peserta ? $peserta->pemainIds() : [];
        }

        if ($pertandingan->id_pemenang) {
            return [(int) $pertandingan->id_pemenang];
        }

        return [];
    }

    public function resolvePemainIdsFromPeserta(?int $pesertaId): array
    {
        if (! $pesertaId) {
            return [];
        }

        $peserta = TurnamenPeserta::find($pesertaId);

        return $peserta ? $peserta->pemainIds() : [];
    }

    public function resolvePemainIdsFromSide(Pertandingan $pertandingan, int $side): array
    {
        if ($side === 1) {
            if ($pertandingan->id_peserta1) {
                return $this->resolvePemainIdsFromPeserta((int) $pertandingan->id_peserta1);
            }

            return $pertandingan->id_pemain1 ? [(int) $pertandingan->id_pemain1] : [];
        }

        if ($pertandingan->id_peserta2) {
            return $this->resolvePemainIdsFromPeserta((int) $pertandingan->id_peserta2);
        }

        return $pertandingan->id_pemain2 ? [(int) $pertandingan->id_pemain2] : [];
    }
}
