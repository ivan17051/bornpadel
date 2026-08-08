<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPublicKategori;
use App\Services\FriendlyMatchmakingService;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use RuntimeException;

class StandingsController extends Controller
{
    use ResolvesPublicKategori;

    public function index(
        Request $request,
        LeaderboardService $leaderboardService,
        FriendlyMatchmakingService $friendlyService
    ) {
        $turnamenId = $request->input('id_turnamen');
        $turnamen = $turnamenId
            ? \App\Models\Turnamen::find($turnamenId)
            : $leaderboardService->getActiveTournament();

        $kategoriId = null;
        if ($turnamen) {
            try {
                if ($turnamen->hasMultipleKategori() && ! $request->filled('id_kategori')) {
                    $kategoriId = optional($turnamen->defaultKategori())->id;
                } else {
                    $kategoriId = $this->resolveApiKategori(
                        $turnamen,
                        $request->input('id_kategori')
                    )->id;
                }
            } catch (RuntimeException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => [],
                ], 422);
            }
        }

        if ($turnamen && $turnamen->isMahjong()) {
            $mahjongStandings = $leaderboardService->getMahjongStandingsByBabak($turnamen->id, $kategoriId);

            if ($mahjongStandings['sections']->isEmpty() && $mahjongStandings['recap']->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada data klasemen.',
                    'type' => 'mahjong',
                    'data' => $mahjongStandings,
                ]);
            }

            return response()->json([
                'success' => true,
                'type' => 'mahjong',
                'data' => $mahjongStandings,
            ]);
        }

        if ($turnamen && $turnamen->isFriendly()) {
            $standings = $leaderboardService->getFriendlyStandings($turnamen->id, $kategoriId);
            $matches = $friendlyService->getPublicMatchSessions($turnamen, $kategoriId);

            if ($standings->isEmpty() && $matches->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada data klasemen.',
                    'type' => 'friendly',
                    'data' => [],
                    'matches' => [],
                ]);
            }

            return response()->json([
                'success' => true,
                'type' => 'friendly',
                'data' => $standings,
                'matches' => $matches->values(),
            ]);
        }

        $standings = $leaderboardService->getStandings($turnamen ? $turnamen->id : $turnamenId, $kategoriId);

        if ($standings->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data klasemen.',
                'type' => 'group',
                'data' => [],
                'post_league' => [
                    'sections' => [],
                    'has_bracket' => false,
                    'is_double' => false,
                ],
            ]);
        }

        $postLeague = $turnamen && $turnamen->usesKnockoutBracket()
            ? $leaderboardService->getPostLeagueRanking($turnamen->id, $kategoriId)
            : ['sections' => collect(), 'has_bracket' => false, 'is_double' => false];

        return response()->json([
            'success' => true,
            'type' => 'group',
            'data' => $standings,
            'post_league' => [
                'sections' => $postLeague['sections']->values(),
                'has_bracket' => (bool) ($postLeague['has_bracket'] ?? false),
                'is_double' => (bool) ($postLeague['is_double'] ?? false),
            ],
        ]);
    }
}
