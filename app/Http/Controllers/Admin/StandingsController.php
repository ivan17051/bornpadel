<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroupMatchmakingService;
use App\Services\LeaderboardService;

class StandingsController extends Controller
{
    public function index(LeaderboardService $leaderboardService, GroupMatchmakingService $matchmakingService)
    {
        $turnamen = $matchmakingService->getActiveTournament();
        $standings = $leaderboardService->getStandings(optional($turnamen)->id);

        return view('admin.standings.index', compact('turnamen', 'standings'));
    }
}
