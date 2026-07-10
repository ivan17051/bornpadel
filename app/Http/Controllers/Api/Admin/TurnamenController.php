<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyTurnamenRequest;
use App\Models\Turnamen;
use App\Services\TournamentDeletionService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TurnamenController extends Controller
{
    protected $deletionService;

    public function __construct(TournamentDeletionService $deletionService)
    {
        $this->deletionService = $deletionService;
    }

    public function destroy(DestroyTurnamenRequest $request, Turnamen $turnamen): JsonResponse
    {
        try {
            $this->deletionService->delete($turnamen, $request->user(), $request->input('password'));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Turnamen berhasil dihapus.',
            'data' => [
                'id' => $turnamen->id,
            ],
        ]);
    }
}
