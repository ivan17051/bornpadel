<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Services\GroupStageHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GroupStageHistoryController extends Controller
{
    public function show(Request $request, GroupStageHistoryService $historyService): JsonResponse
    {
        $data = $request->validate([
            'id_grup' => ['required', 'integer', 'exists:grup,id'],
            'id_peserta' => ['required', 'integer', 'exists:turnamen_peserta,id'],
        ]);

        try {
            $history = $historyService->getHistory((int) $data['id_grup'], (int) $data['id_peserta']);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }
}
