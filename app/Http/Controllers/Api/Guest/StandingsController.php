<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function index(Request $request, LeaderboardService $leaderboardService)
    {
        $standings = $leaderboardService->getStandings($request->input('id_turnamen'));

        if ($standings->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data klasemen.',
                'data' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $standings,
        ]);
    }
}
