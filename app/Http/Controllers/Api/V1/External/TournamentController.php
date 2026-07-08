<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Models\Turnamen;
use App\Models\TurnamenPemenang;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    protected $leaderboardService;

    public function __construct(LeaderboardService $leaderboardService)
    {
        $this->leaderboardService = $leaderboardService;
    }

    public function mahjongList(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:open,ongoing,completed,draft'],
        ]);

        $query = Turnamen::query()
            ->where('jenis', 'mahjong')
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $turnamen = $query->get()->map(function (Turnamen $item) {
            return [
                'id' => $item->id,
                'nama' => $item->nama,
                'tanggal' => optional($item->tanggal)->toDateString(),
                'harga' => $item->harga,
                'syarat' => $item->syarat,
                'jenis' => $item->jenis,
                'jenis_label' => $item->jenis_label,
                'status' => $item->status,
                'mahjong_is_final' => (bool) $item->mahjong_is_final,
                'registration_open' => $item->isRegistrationOpen(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $turnamen,
        ]);
    }

    public function groupStandings(int $id): JsonResponse
    {
        $turnamen = Turnamen::find($id);

        if (! $turnamen) {
            return response()->json([
                'success' => false,
                'message' => 'Turnamen tidak ditemukan.',
            ], 404);
        }

        if (! $turnamen->isMahjong()) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint ini hanya tersedia untuk turnamen Mahjong.',
            ], 422);
        }

        $standings = $this->leaderboardService->getMahjongStandingsByBabak($turnamen->id);

        return response()->json([
            'success' => true,
            'data' => [
                'turnamen' => [
                    'id' => $turnamen->id,
                    'nama' => $turnamen->nama,
                    'jenis' => $turnamen->jenis,
                    'status' => $turnamen->status,
                    'mahjong_is_final' => (bool) $turnamen->mahjong_is_final,
                ],
                'sections' => $standings['sections'],
                'recap' => $standings['recap'],
                'babak_numbers' => $standings['babak_numbers'],
            ],
        ]);
    }

    public function winners(int $id): JsonResponse
    {
        $turnamen = Turnamen::with(['pemenang.pemain'])->find($id);

        if (! $turnamen) {
            return response()->json([
                'success' => false,
                'message' => 'Turnamen tidak ditemukan.',
            ], 404);
        }

        if ($turnamen->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Turnamen belum selesai.',
            ], 422);
        }

        $winners = $turnamen->pemenang->map(function (TurnamenPemenang $row) {
            return [
                'peringkat' => $row->peringkat,
                'label' => 'Juara ' . $row->peringkat,
                'id_pemain' => $row->id_pemain,
                'nama' => optional($row->pemain)->nama,
                'foto_url' => optional($row->pemain)->foto_url,
                'total_poin' => (int) $row->total_poin,
            ];
        })->values();

        if ($winners->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data juara belum tersedia.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'turnamen' => [
                    'id' => $turnamen->id,
                    'nama' => $turnamen->nama,
                    'jenis' => $turnamen->jenis,
                    'status' => $turnamen->status,
                ],
                'winners' => $winners,
            ],
        ]);
    }
}
