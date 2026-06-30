<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Models\Turnamen;
use App\Models\TurnamenPemenang;
use App\Services\MahjongMatchmakingService;
use Illuminate\Http\JsonResponse;

class TournamentController extends Controller
{
    protected $mahjongService;

    public function __construct(MahjongMatchmakingService $mahjongService)
    {
        $this->mahjongService = $mahjongService;
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

        return response()->json([
            'success' => true,
            'data' => $this->mahjongService->getGroupStandingsPayload($turnamen),
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
