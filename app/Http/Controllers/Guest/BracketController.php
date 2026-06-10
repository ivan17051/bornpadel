<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\KnockoutBracketService;
use App\Services\LeaderboardService;

class BracketController extends Controller
{
    public function index(KnockoutBracketService $bracketService, LeaderboardService $leaderboardService)
    {
        $turnamen = $leaderboardService->getActiveTournament();
        $bracket = $turnamen ? $bracketService->getBracketTree($turnamen) : [];

        return view('guest.bracket', compact('turnamen', 'bracket'));
    }
}
