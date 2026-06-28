<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Services\PemainRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'data' => $this->formatTournament($turnamen),
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $turnamen = $this->registrationService->getOpenTournaments()
            ->map(function ($item) {
                return $this->formatTournament($item);
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $turnamen,
        ]);
    }

    protected function formatTournament($turnamen): array
    {
        return [
            'id' => $turnamen->id,
            'nama' => $turnamen->nama,
            'harga' => $turnamen->harga,
            'harga_formatted' => 'Rp ' . number_format($turnamen->harga, 0, ',', '.'),
            'syarat' => $turnamen->syarat,
            'jenis' => $turnamen->jenis,
            'jenis_label' => $turnamen->jenis_label,
            'status' => $turnamen->status,
            'doc' => optional($turnamen->doc)->toIso8601String(),
        ];
    }
}
