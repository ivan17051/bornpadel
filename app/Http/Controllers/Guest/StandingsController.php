<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use App\Services\PemainRegistrationService;

class StandingsController extends Controller
{
    public function index(LeaderboardService $leaderboardService, PemainRegistrationService $registrationService)
    {
        $turnamen = $registrationService->getActiveTournament()
            ?? $leaderboardService->getActiveTournament();

        $standings = $leaderboardService->getStandings(optional($turnamen)->id);

        return view('guest.standings', compact('turnamen', 'standings'));
    }
}
