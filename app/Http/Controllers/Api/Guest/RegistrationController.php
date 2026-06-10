<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Services\PemainRegistrationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    protected $registrationService;

    public function __construct(PemainRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function store(StorePemainRegistrationRequest $request): JsonResponse
    {
        $turnamen = $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran ditutup. Tidak ada turnamen aktif.',
            ], 422);
        }

        $pemain = $this->registrationService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil! Tim kami akan memverifikasi data Anda.',
            'data' => [
                'id' => $pemain->id,
                'nama' => $pemain->nama,
                'no_hp' => $pemain->no_hp,
                'status' => $pemain->status,
                'turnamen' => [
                    'id' => $turnamen->id,
                    'nama' => $turnamen->nama,
                ],
            ],
        ], 201);
    }
}
