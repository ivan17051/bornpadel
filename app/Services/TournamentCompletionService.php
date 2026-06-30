<?php

namespace App\Services;

use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPemenang;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TournamentCompletionService
{
    protected $pointRewardService;

    public function __construct(PointRewardService $pointRewardService)
    {
        $this->pointRewardService = $pointRewardService;
    }

    public function canComplete(Turnamen $turnamen): bool
    {
        if ($turnamen->status === 'completed') {
            return false;
        }

        if ($turnamen->isMahjong()) {
            return app(MahjongMatchmakingService::class)->canComplete($turnamen);
        }

        $final = $this->getFinalMatch($turnamen);

        return $final && $final->status === 'completed';
    }

    public function complete(Turnamen $turnamen): array
    {
        if ($turnamen->isMahjong()) {
            return $this->completeMahjong($turnamen);
        }

        if (! $this->canComplete($turnamen)) {
            throw new RuntimeException('Turnamen belum dapat diselesaikan. Pastikan pertandingan Final sudah selesai.');
        }

        return DB::transaction(function () use ($turnamen) {
            $placements = $this->resolvePlacements($turnamen);
            $placementConfig = config('tournament.points.placement', []);
            $awards = [];

            foreach ([1, 2, 3] as $place) {
                $pemainIds = $placements[$place] ?? [];

                if ($pemainIds === []) {
                    continue;
                }

                $awards[] = [
                    'place' => $place,
                    'pemain_ids' => $pemainIds,
                    'points' => (int) ($placementConfig[$place] ?? 0),
                ];
            }

            $this->pointRewardService->awardPlacementPoints($awards);

            $turnamen->update(['status' => 'completed']);

            return [
                'turnamen' => $turnamen->fresh(),
                'placements' => $placements,
                'awards' => $awards,
            ];
        });
    }

    protected function getFinalMatch(Turnamen $turnamen): ?Pertandingan
    {
        return Pertandingan::where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Final')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int, int[]>
     */
    protected function resolvePlacements(Turnamen $turnamen): array
    {
        $final = $this->getFinalMatch($turnamen);

        if (! $final || $final->status !== 'completed') {
            throw new RuntimeException('Pertandingan Final belum selesai.');
        }

        $firstIds = $this->pointRewardService->resolveWinnerPemainIds($final);
        $secondIds = $this->resolveLoserIds($final);
        $thirdIds = $this->resolveThirdPlaceIds($turnamen, $final, $secondIds);

        return [
            1 => $firstIds,
            2 => $secondIds,
            3 => $thirdIds,
        ];
    }

    protected function resolveLoserIds(Pertandingan $final): array
    {
        $winnerIds = $this->pointRewardService->resolveWinnerPemainIds($final);

        foreach ([1, 2] as $side) {
            $sideIds = $this->pointRewardService->resolvePemainIdsFromSide($final, $side);

            if ($sideIds === []) {
                continue;
            }

            if (array_diff($sideIds, $winnerIds) === $sideIds) {
                return $sideIds;
            }
        }

        return [];
    }

    protected function resolveThirdPlaceIds(Turnamen $turnamen, Pertandingan $final, array $secondPlaceIds): array
    {
        $semifinals = Pertandingan::where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Semifinal')
            ->where('status', 'completed')
            ->get();

        if ($semifinals->isEmpty()) {
            return [];
        }

        $finalistIds = array_unique(array_merge(
            $this->pointRewardService->resolveWinnerPemainIds($final),
            $secondPlaceIds
        ));

        $candidates = [];

        foreach ($semifinals as $semifinal) {
            $loserIds = $this->resolveLoserIds($semifinal);

            if ($loserIds === [] || array_intersect($loserIds, $finalistIds) !== []) {
                continue;
            }

            $candidates[] = [
                'pemain_ids' => $loserIds,
                'poin_didapat' => $this->resolveGroupPointsForSide($semifinal, $loserIds),
            ];
        }

        if ($candidates === []) {
            return [];
        }

        usort($candidates, function (array $a, array $b) {
            return $b['poin_didapat'] <=> $a['poin_didapat'];
        });

        return $candidates[0]['pemain_ids'];
    }

  /**
     * @param  int[]  $pemainIds
     */
    protected function resolveGroupPointsForSide(Pertandingan $pertandingan, array $pemainIds): int
    {
        $pesertaId = null;

        foreach ([1, 2] as $side) {
            $sideIds = $this->pointRewardService->resolvePemainIdsFromSide($pertandingan, $side);

            if ($sideIds !== [] && array_diff($sideIds, $pemainIds) === []) {
                $pesertaId = $side === 1 ? $pertandingan->id_peserta1 : $pertandingan->id_peserta2;
                break;
            }
        }

        if ($pesertaId) {
            $member = GrupMember::where('id_turnamen_peserta', $pesertaId)->first();

            return $member ? (int) $member->poin_didapat : 0;
        }

        $member = GrupMember::where('id_pemain', $pemainIds[0] ?? 0)->first();

        return $member ? (int) $member->poin_didapat : 0;
    }

    protected function completeMahjong(Turnamen $turnamen): array
    {
        $mahjongService = app(MahjongMatchmakingService::class);

        if (! $mahjongService->canComplete($turnamen)) {
            throw new RuntimeException('Turnamen Mahjong belum dapat diselesaikan. Pastikan grup final berisi 4 pemain.');
        }

        return DB::transaction(function () use ($turnamen, $mahjongService) {
            $mahjongService->commitCurrentRoundPoints($turnamen);
            $placements = $mahjongService->resolveFinalPlacements($turnamen);
            $placementConfig = config('tournament.points.placement', []);
            $awards = [];

            TurnamenPemenang::where('id_turnamen', $turnamen->id)->delete();

            foreach ([1, 2, 3] as $place) {
                $placement = $placements[$place] ?? null;

                if (! $placement) {
                    continue;
                }

                TurnamenPemenang::create([
                    'id_turnamen' => $turnamen->id,
                    'peringkat' => $place,
                    'id_pemain' => $placement['pemain_ids'][0],
                    'id_turnamen_peserta' => $placement['peserta_id'],
                    'total_poin' => $placement['total_poin'],
                ]);

                $awards[] = [
                    'place' => $place,
                    'pemain_ids' => $placement['pemain_ids'],
                    'points' => (int) ($placementConfig[$place] ?? 0),
                ];
            }

            $this->pointRewardService->awardPlacementPoints($awards);
            $turnamen->update(['status' => 'completed']);
            $turnamen->activeGrup()->update(['is_aktif' => false]);

            return [
                'turnamen' => $turnamen->fresh(),
                'placements' => $placements,
                'awards' => $awards,
            ];
        });
    }
}
