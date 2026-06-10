<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Services\PemainRegistrationService;
use Illuminate\Http\JsonResponse;

class TournamentController extends Controller
{
    protected $registrationService;

    public function __construct(PemainRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function active(): JsonResponse
    {
        $turnamen = $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada turnamen yang sedang dibuka.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $turnamen->id,
                'nama' => $turnamen->nama,
                'harga' => $turnamen->harga,
                'harga_formatted' => 'Rp ' . number_format($turnamen->harga, 0, ',', '.'),
                'syarat' => $turnamen->syarat,
                'status' => $turnamen->status,
                'doc' => optional($turnamen->doc)->toIso8601String(),
            ],
        ]);
    }
}
