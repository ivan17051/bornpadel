<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use Illuminate\Http\Request;

class BracketController extends Controller
{
    public function index(
        Request $request,
        KnockoutBracketService $bracketService,
        GroupMatchmakingService $matchmakingService
    ) {
        $turnamenList = $matchmakingService->listForFilter();
        $turnamen = $matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );
        $bracket = $turnamen ? $bracketService->getBracketTree($turnamen) : [];

        return view('admin.bracket.index', compact('turnamen', 'turnamenList', 'bracket'));
    }
}
