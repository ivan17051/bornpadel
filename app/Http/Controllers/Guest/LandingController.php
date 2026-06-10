<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\KnockoutBracketService;
use App\Services\LeaderboardService;
use App\Services\PemainRegistrationService;

class LandingController extends Controller
{
    protected $registrationService;

    public function __construct(PemainRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function index(LeaderboardService $leaderboardService, KnockoutBracketService $bracketService)
    {
        $turnamen = $this->registrationService->getActiveTournament();
        $standings = collect();
        $bracket = [];

        if ($turnamen && in_array($turnamen->status, ['ongoing', 'completed'], true)) {
            $standings = $leaderboardService->getStandings($turnamen->id);

            if ($bracketService->hasKnockoutBracket($turnamen)) {
                $bracket = $bracketService->getBracketTree($turnamen);
            }
        }

        return view('guest.landing', compact('turnamen', 'standings', 'bracket'));
    }
}
