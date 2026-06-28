<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\KnockoutBracketService;
use App\Services\PemainRegistrationService;
use Illuminate\Http\Request;

class BracketController extends Controller
{
    public function index(
        Request $request,
        KnockoutBracketService $bracketService,
        PemainRegistrationService $registrationService
    ) {
        $turnamen = $registrationService->resolvePublicTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        $bracket = $turnamen ? $bracketService->getBracketTree($turnamen) : [];

        return view('guest.bracket', compact('turnamen', 'bracket'));
    }
}
