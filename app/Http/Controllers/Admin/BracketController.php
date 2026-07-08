<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\TournamentAccessService;
use Illuminate\Http\Request;
use RuntimeException;

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

    public function swap(
        Request $request,
        KnockoutBracketService $bracketService,
        GroupMatchmakingService $matchmakingService,
        TournamentAccessService $tournamentAccess
    ) {
        $request->validate([
            'id_turnamen' => ['nullable', 'exists:m_turnamen,id'],
            'source_match' => ['required', 'integer'],
            'source_slot' => ['required', 'integer', 'in:1,2'],
            'target_match' => ['required', 'integer'],
            'target_slot' => ['required', 'integer', 'in:1,2'],
        ]);

        $turnamen = $matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );

        if (! $turnamen) {
            return response()->json([
                'success' => false,
                'message' => 'Turnamen tidak ditemukan.',
            ], 422);
        }

        $tournamentAccess->assertTurnamenId((int) $turnamen->id);

        try {
            $result = $bracketService->swapParticipants(
                $turnamen,
                (int) $request->source_match,
                (int) $request->source_slot,
                (int) $request->target_match,
                (int) $request->target_slot
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Peserta bracket berhasil ditukar.',
            'data' => $result,
        ]);
    }
}
