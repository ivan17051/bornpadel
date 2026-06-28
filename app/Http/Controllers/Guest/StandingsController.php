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

        $standings = $leaderboardService->getStandings(optional($turnamen)->id);

        return view('guest.standings', compact('turnamen', 'standings'));
    }
}
