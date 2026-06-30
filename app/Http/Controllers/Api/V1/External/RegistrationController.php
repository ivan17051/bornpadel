<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\External\RegisterPlayerRequest;
use App\Models\Turnamen;
use App\Services\PemainRegistrationService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RegistrationController extends Controller
{
    protected $registrationService;

    public function __construct(PemainRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function registerPlayer(RegisterPlayerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $turnamen = Turnamen::findOrFail($data['id_turnamen']);

        if (! $turnamen->isRegistrationOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran turnamen tidak dibuka.',
            ], 422);
        }

        if ($turnamen->isDouble()) {
            return response()->json([
                'success' => false,
                'message' => 'Turnamen double memerlukan pendaftaran pasangan melalui admin.',
            ], 422);
        }

        try {
            $pemain = $this->registrationService->register(
                $turnamen,
                $data,
                null,
                null
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($request->filled('status') && $request->status !== 'pending') {
            $peserta = $pemain->pesertaForTurnamen($turnamen);

            if ($peserta) {
                $peserta->update([
                    'status' => $request->status,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Pemain berhasil didaftarkan.',
            'data' => [
                'turnamen_id' => $turnamen->id,
                'pemain_id' => $pemain->id,
                'nama' => $pemain->nama,
                'no_hp' => $pemain->no_hp,
                'status' => optional($pemain->pesertaForTurnamen($turnamen))->status,
            ],
        ], 201);
    }
}
