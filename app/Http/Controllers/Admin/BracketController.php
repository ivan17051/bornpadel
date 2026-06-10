<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;

class BracketController extends Controller
{
    public function index(
        KnockoutBracketService $bracketService,
        GroupMatchmakingService $matchmakingService
    ) {
        $turnamen = $matchmakingService->getActiveTournament();
        $bracket = $turnamen ? $bracketService->getBracketTree($turnamen) : [];

        return view('admin.bracket.index', compact('turnamen', 'bracket'));
    }
}
