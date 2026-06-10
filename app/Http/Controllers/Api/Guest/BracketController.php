<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Models\Turnamen;
use App\Services\KnockoutBracketService;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;

class BracketController extends Controller
{
    public function index(Request $request, KnockoutBracketService $bracketService, LeaderboardService $leaderboardService)
    {
        $turnamen = $request->filled('id_turnamen')
            ? Turnamen::find($request->input('id_turnamen'))
            : $leaderboardService->getActiveTournament();

        if (! $turnamen) {
            return response()->json([
                'success' => false,
                'message' => 'Turnamen tidak ditemukan.',
                'data' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'turnamen' => $turnamen->only(['id', 'nama', 'status']),
                'bracket' => $bracketService->getBracketTree($turnamen),
            ],
        ]);
    }
}
