<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Models\TurnamenPeserta;
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
        $turnamen = app(PemainRegistrationService::class)->resolveOpenTournament(
            $request->input('id_turnamen') ? (int) $request->input('id_turnamen') : null
        ) ?? $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran ditutup. Tidak ada turnamen aktif.',
            ], 422);
        }

        $buktiBayar = $request->file('bukti_bayar');

        try {
            if ($turnamen->isDouble() && $request->isPairRegistration()) {
                $pair = $this->registrationService->registerPair(
                    $turnamen,
                    $request->playerOnePayload(),
                    $request->file('foto'),
                    $request->playerTwoPayload(),
                    $request->file('foto_2'),
                    $buktiBayar,
                    TurnamenPeserta::SUMBER_INTERNAL,
                    false
                );

                $pemain = $pair['pemain'];
                $partner = $pair['partner'];
                $peserta = TurnamenPeserta::query()
                    ->forTurnamen($turnamen->id)
                    ->where('id_pemain1', $pemain->id)
                    ->first();
                $pasangan = optional($peserta)->pasangan;
            } else {
                $pemain = $this->registrationService->register(
                    $turnamen,
                    $request->playerOnePayload(),
                    $request->file('foto'),
                    $buktiBayar,
                    TurnamenPeserta::SUMBER_INTERNAL,
                    false
                );
                $partner = null;
                $pasangan = null;
            }
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $status = $this->registrationService->getRegistrationStatus($pemain, $turnamen);

        return response()->json([
            'success' => true,
            'message' => $partner
                ? 'Pendaftaran berpasangan berhasil! Tim kami akan memverifikasi data Anda.'
                : ($turnamen->isDouble()
                    ? 'Pendaftaran individu berhasil! Pasangan akan dibuat otomatis setelah pendaftaran ditutup.'
                    : 'Pendaftaran berhasil! Tim kami akan memverifikasi data Anda.'),
            'data' => [
                'registration_type' => $partner ? 'pair' : 'single',
                'pemain' => [
                    'id' => $pemain->id,
                    'nama' => $pemain->nama,
                    'no_hp' => $pemain->no_hp,
                    'status' => $status,
                ],
                'partner' => $partner ? [
                    'id' => $partner->id,
                    'nama' => $partner->nama,
                    'no_hp' => $partner->no_hp,
                    'status' => $this->registrationService->getRegistrationStatus($partner, $turnamen),
                ] : null,
                'pasangan_id' => optional($pasangan)->id,
                'turnamen' => [
                    'id' => $turnamen->id,
                    'nama' => $turnamen->nama,
                    'jenis' => $turnamen->jenis,
                ],
            ],
        ], 201);
    }
}
