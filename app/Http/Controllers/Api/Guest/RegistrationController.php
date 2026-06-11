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

        try {
            $pemain = $this->registrationService->register(
                $turnamen,
                $request->validated(),
                $request->file('foto')
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil! Tim kami akan memverifikasi data Anda.',
            'data' => [
                'id' => $pemain->id,
                'nama' => $pemain->nama,
                'no_hp' => $pemain->no_hp,
                'status' => $this->registrationService->getRegistrationStatus($pemain, $turnamen),
                'turnamen' => [
                    'id' => $turnamen->id,
                    'nama' => $turnamen->nama,
                ],
            ],
        ], 201);
    }
}
