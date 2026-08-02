<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function index(Request $request, LeaderboardService $leaderboardService)
    {
        $turnamenId = $request->input('id_turnamen');
        $turnamen = $turnamenId
            ? \App\Models\Turnamen::find($turnamenId)
            : $leaderboardService->getActiveTournament();

        if ($turnamen && $turnamen->isMahjong()) {
            $mahjongStandings = $leaderboardService->getMahjongStandingsByBabak($turnamen->id);

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
            $standings = $leaderboardService->getFriendlyStandings($turnamen->id);

            if ($standings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada data klasemen.',
                    'type' => 'friendly',
                    'data' => [],
                ]);
            }

            return response()->json([
                'success' => true,
                'type' => 'friendly',
                'data' => $standings,
            ]);
        }

        $standings = $leaderboardService->getStandings($turnamenId);

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
            ? $leaderboardService->getPostLeagueRanking($turnamen->id)
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
