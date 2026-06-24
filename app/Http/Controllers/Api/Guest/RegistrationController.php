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

        $validated = $request->validated();

        try {
            if ($turnamen->isDouble()) {
                $result = $this->registrationService->registerPair(
                    $turnamen,
                    $validated,
                    $request->file('foto'),
                    [
                        'no_hp' => $validated['partner_no_hp'],
                        'nama' => $validated['partner_nama'],
                        'tgl_lahir' => $validated['partner_tgl_lahir'],
                        'gender' => $validated['partner_gender'],
                        'rating' => $validated['partner_rating'] ?? null,
                    ],
                    $request->file('partner_foto')
                );

                $pemain = $result['pemain'];
                $partner = $result['partner'];

                return response()->json([
                    'success' => true,
                    'message' => 'Pendaftaran berhasil! Tim kami akan memverifikasi data Anda.',
                    'data' => [
                        'pemain' => [
                            'id' => $pemain->id,
                            'nama' => $pemain->nama,
                            'no_hp' => $pemain->no_hp,
                            'status' => $this->registrationService->getRegistrationStatus($pemain, $turnamen),
                        ],
                        'partner' => [
                            'id' => $partner->id,
                            'nama' => $partner->nama,
                            'no_hp' => $partner->no_hp,
                            'status' => $this->registrationService->getRegistrationStatus($partner, $turnamen),
                        ],
                        'turnamen' => [
                            'id' => $turnamen->id,
                            'nama' => $turnamen->nama,
                            'jenis' => $turnamen->jenis,
                        ],
                    ],
                ], 201);
            }

            $pemain = $this->registrationService->register(
                $turnamen,
                $validated,
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
                    'jenis' => $turnamen->jenis,
                ],
            ],
        ], 201);
    }
}
