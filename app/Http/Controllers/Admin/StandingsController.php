<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroupMatchmakingService;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function index(
        Request $request,
        LeaderboardService $leaderboardService,
        GroupMatchmakingService $matchmakingService
    ) {
        $turnamenList = $matchmakingService->listForFilter();
        $turnamen = $matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );
        $standings = $turnamen
            ? $leaderboardService->getStandings($turnamen->id)
            : collect();

        return view('admin.standings.index', compact('turnamen', 'turnamenList', 'standings'));
    }
}
