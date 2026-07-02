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
        $mahjongStandings = $turnamen && $turnamen->isMahjong()
            ? $leaderboardService->getMahjongStandingsByBabak($turnamen->id)
            : ['sections' => collect(), 'overall' => collect()];
        $standings = $turnamen
            ? ($turnamen->isMahjong()
                ? $mahjongStandings['sections']
                : $leaderboardService->getStandings($turnamen->id))
            : collect();
        $mahjongOverall = $turnamen && $turnamen->isMahjong()
            ? $mahjongStandings['overall']
            : collect();

        return view('admin.standings.index', compact('turnamen', 'turnamenList', 'standings', 'mahjongOverall'));
    }
}
