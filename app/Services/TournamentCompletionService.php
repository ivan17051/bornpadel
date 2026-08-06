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

    public function canComplete(Turnamen $turnamen, $idKategori = null): bool
    {
        if ($turnamen->status === 'completed') {
            return false;
        }

        if ($turnamen->isMahjong()) {
            return app(MahjongMatchmakingService::class)->canComplete($turnamen, $idKategori);
        }

        if ($turnamen->isFriendly()) {
            $kategori = $turnamen->resolveKategori($idKategori);

            return $kategori->status === 'ongoing'
                && $turnamen->competitionGrup($kategori->id)->exists()
                && $turnamen->competitionPertandingan($kategori->id)
                    ->where('nama_ronde', 'Friendly')
                    ->where('status', 'completed')
                    ->exists();
        }

        $final = $this->getFinalMatch($turnamen, $idKategori);

        return $final && $final->status === 'completed';
    }

    public function hasPendingThirdPlacePlayoff(Turnamen $turnamen, $idKategori = null): bool
    {
        if ($turnamen->isMahjong() || $turnamen->isFriendly()) {
            return false;
        }

        $thirdPlace = $this->getThirdPlaceMatch($turnamen, $idKategori);

        return $thirdPlace
            && $thirdPlace->status !== 'completed'
            && $thirdPlace->status !== 'cancelled'
            && $thirdPlace->isReadyForScoring();
    }

    public function complete(Turnamen $turnamen, $idKategori = null): array
    {
        if ($turnamen->isMahjong()) {
            return $this->completeMahjong($turnamen, $idKategori);
        }

        if ($turnamen->isFriendly()) {
            return $this->completeFriendly($turnamen, $idKategori);
        }

        if (! $this->canComplete($turnamen, $idKategori)) {
            throw new RuntimeException('Turnamen belum dapat diselesaikan. Pastikan pertandingan Final sudah selesai.');
        }

        return DB::transaction(function () use ($turnamen, $idKategori) {
            $cancelledThirdPlace = $this->cancelUnfinishedThirdPlacePlayoff($turnamen, $idKategori);
            $placements = $this->resolvePlacements($turnamen, $idKategori);
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

            $kategori = $turnamen->resolveKategori($idKategori);
            $kategori->update(['status' => 'completed']);

            if ($turnamen->kategori()->count() <= 1
                || ! $turnamen->kategori()->where('status', '!=', 'completed')->exists()) {
                $turnamen->update(['status' => 'completed']);
            }

            return [
                'turnamen' => $turnamen->fresh(),
                'kategori' => $kategori->fresh(),
                'placements' => $placements,
                'awards' => $awards,
                'cancelled_third_place' => $cancelledThirdPlace,
            ];
        });
    }

    protected function cancelUnfinishedThirdPlacePlayoff(Turnamen $turnamen, $idKategori = null): bool
    {
        $thirdPlace = $this->getThirdPlaceMatch($turnamen, $idKategori);

        if (! $thirdPlace || in_array($thirdPlace->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $thirdPlace->update(['status' => 'cancelled']);

        return true;
    }

    protected function completeFriendly(Turnamen $turnamen, $idKategori = null): array
    {
        if (! $this->canComplete($turnamen, $idKategori)) {
            throw new RuntimeException('Turnamen Friendly belum dapat diselesaikan. Minimal satu pertandingan selesai diperlukan.');
        }

        return DB::transaction(function () use ($turnamen, $idKategori) {
            $kategori = $turnamen->resolveKategori($idKategori);
            $standings = app(LeaderboardService::class)->getFriendlyStandings($turnamen->id, $kategori->id);

            $kategori->update(['status' => 'completed']);

            if ($turnamen->kategori()->count() <= 1
                || ! $turnamen->kategori()->where('status', '!=', 'completed')->exists()) {
                $turnamen->update(['status' => 'completed']);
            }

            return [
                'turnamen' => $turnamen->fresh(),
                'kategori' => $kategori->fresh(),
                'placements' => $standings->take(3)->values(),
                'awards' => [],
            ];
        });
    }

    protected function getFinalMatch(Turnamen $turnamen, $idKategori = null): ?Pertandingan
    {
        return Pertandingan::where('id_kategori', $turnamen->resolveKategori($idKategori)->id)
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Final')
            ->orderByDesc('id')
            ->first();
    }

    protected function getThirdPlaceMatch(Turnamen $turnamen, $idKategori = null): ?Pertandingan
    {
        return Pertandingan::where('id_kategori', $turnamen->resolveKategori($idKategori)->id)
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Perebutan Juara 3')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int, int[]>
     */
    protected function resolvePlacements(Turnamen $turnamen, $idKategori = null): array
    {
        $final = $this->getFinalMatch($turnamen, $idKategori);

        if (! $final || $final->status !== 'completed') {
            throw new RuntimeException('Pertandingan Final belum selesai.');
        }

        $firstIds = $this->pointRewardService->resolveWinnerPemainIds($final);
        $secondIds = $this->resolveLoserIds($final);
        $thirdIds = $this->resolveThirdPlaceIds($turnamen, $final, $secondIds, $idKategori);

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

    protected function resolveThirdPlaceIds(Turnamen $turnamen, Pertandingan $final, array $secondPlaceIds, $idKategori = null): array
    {
        // Prefer the explicit third-place playoff winner when it has been played.
        $thirdPlace = $this->getThirdPlaceMatch($turnamen, $idKategori);

        if ($thirdPlace && $thirdPlace->status === 'completed') {
            $winnerIds = $this->pointRewardService->resolveWinnerPemainIds($thirdPlace);

            if ($winnerIds !== []) {
                return $winnerIds;
            }
        }

        $semifinals = Pertandingan::where('id_kategori', $turnamen->resolveKategori()->id)
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

    protected function completeMahjong(Turnamen $turnamen, $idKategori = null): array
    {
        $mahjongService = app(MahjongMatchmakingService::class);

        if (! $mahjongService->canComplete($turnamen, $idKategori)) {
            throw new RuntimeException('Turnamen Mahjong belum dapat diselesaikan. Pastikan grup final berisi 4 pemain.');
        }

        return DB::transaction(function () use ($turnamen, $mahjongService, $idKategori) {
            $kategori = $turnamen->resolveKategori($idKategori);
            $mahjongService->commitCurrentRoundPoints($turnamen, $kategori->id);
            $placements = $mahjongService->resolveFinalPlacements($turnamen, $kategori->id);
            $placementConfig = config('tournament.points.placement', []);
            $awards = [];

            TurnamenPemenang::where('id_kategori', $kategori->id)->delete();

            foreach ([1, 2, 3] as $place) {
                $placement = $placements[$place] ?? null;

                if (! $placement) {
                    continue;
                }

                TurnamenPemenang::create([
                    'id_turnamen' => $turnamen->id,
                    'id_kategori' => $kategori->id,
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
            $kategori->update(['status' => 'completed', 'mahjong_is_final' => true]);
            $turnamen->competitionActiveGrup($kategori->id)->update(['is_aktif' => false]);

            if ($turnamen->kategori()->count() <= 1
                || ! $turnamen->kategori()->where('status', '!=', 'completed')->exists()) {
                $turnamen->update(['status' => 'completed']);
            }

            return [
                'turnamen' => $turnamen->fresh(),
                'kategori' => $kategori->fresh(),
                'placements' => $placements,
                'awards' => $awards,
            ];
        });
    }
}
