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

            if ($mahjongStandings['sections']->isEmpty() && $mahjongStandings['overall']->isEmpty()) {
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

        $standings = $leaderboardService->getStandings($turnamenId);

        if ($standings->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data klasemen.',
                'type' => 'group',
                'data' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'type' => 'group',
            'data' => $standings,
        ]);
    }
}
