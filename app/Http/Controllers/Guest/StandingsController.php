<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use App\Services\PemainRegistrationService;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function index(
        Request $request,
        LeaderboardService $leaderboardService,
        PemainRegistrationService $registrationService
    ) {
        $turnamen = $registrationService->resolvePublicTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        $mahjongStandings = $turnamen && $turnamen->isMahjong()
            ? $leaderboardService->getMahjongStandingsByBabak($turnamen->id)
            : ['sections' => collect(), 'overall' => collect()];
        $standings = $turnamen && $turnamen->isMahjong()
            ? $mahjongStandings['sections']
            : $leaderboardService->getStandings(optional($turnamen)->id);
        $mahjongOverall = $turnamen && $turnamen->isMahjong()
            ? $mahjongStandings['overall']
            : collect();

        return view('guest.standings', compact('turnamen', 'standings', 'mahjongOverall'));
    }
}
