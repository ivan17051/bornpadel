<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPublicKategori;
use App\Models\Turnamen;
use App\Services\KnockoutBracketService;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use RuntimeException;

class BracketController extends Controller
{
    use ResolvesPublicKategori;

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

        try {
            if ($turnamen->hasMultipleKategori() && ! $request->filled('id_kategori')) {
                $kategori = $turnamen->defaultKategori();
            } else {
                $kategori = $this->resolveApiKategori($turnamen, $request->input('id_kategori'));
            }
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'turnamen' => $turnamen->only(['id', 'nama', 'status']),
                'kategori' => $kategori ? $kategori->only(['id', 'nama', 'status']) : null,
                'bracket' => $bracketService->getBracketTree($turnamen, $kategori ? $kategori->id : null),
            ],
        ]);
    }
}
