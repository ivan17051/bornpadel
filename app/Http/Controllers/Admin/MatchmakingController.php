<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Turnamen;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use Illuminate\Http\Request;
use RuntimeException;

class MatchmakingController extends Controller
{
    protected $matchmakingService;
    protected $knockoutBracketService;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        KnockoutBracketService $knockoutBracketService
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->knockoutBracketService = $knockoutBracketService;
    }

    public function index(Request $request)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $turnamen = $this->matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );
        $approvedCount = $turnamen
            ? $this->matchmakingService->countApprovedPlayers($turnamen)
            : 0;

        $grup = collect();
        if ($turnamen) {
            $grup = $turnamen->grup()
                ->with(['pemain', 'pertandingan.pemain1', 'pertandingan.pemain2'])
                ->get();
        }

        return view('admin.matchmaking.index', [
            'turnamen' => $turnamen,
            'turnamenList' => $turnamenList,
            'approvedCount' => $approvedCount,
            'grup' => $grup,
            'canCloseRegistration' => $turnamen ? $this->matchmakingService->canCloseRegistration($turnamen) : false,
            'canRandomGrup' => $turnamen ? $this->matchmakingService->canGenerateRandomGroups($turnamen) : false,
            'canEndGroupStage' => $turnamen ? $this->knockoutBracketService->canEndGroupStage($turnamen) : false,
            'hasKnockoutBracket' => $turnamen ? $this->knockoutBracketService->hasKnockoutBracket($turnamen) : false,
        ]);
    }

    public function endGroupStage(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $result = $this->knockoutBracketService->generateKnockoutBracket($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Bracket knockout berhasil dibuat (%s) dengan %d pertandingan.',
                implode(' → ', $result['rounds']),
                $result['matches_created']
            ),
            'data' => $result,
        ]);
    }

    public function closeRegistration(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $turnamen = $this->matchmakingService->closeRegistration($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil ditutup. Status turnamen: ongoing.',
            'data' => $turnamen,
        ]);
    }

    public function randomGrup(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $result = $this->matchmakingService->generateRandomGroups($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Berhasil membuat %d grup dan %d pertandingan fase grup.',
                count($result['groups']),
                $result['matches']
            ),
            'data' => $result,
        ]);
    }

    protected function resolveTournament(Request $request): Turnamen
    {
        $request->validate([
            'id_turnamen' => ['nullable', 'exists:turnamen,id'],
        ]);

        if ($request->filled('id_turnamen')) {
            return Turnamen::findOrFail($request->id_turnamen);
        }

        $turnamen = $this->matchmakingService->getActiveTournament();

        if (! $turnamen) {
            throw new RuntimeException('Tidak ada turnamen aktif.');
        }

        return $turnamen;
    }
}
