<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\External\CheckRegistrationRequest;
use App\Http\Requests\Api\External\RegisterPlayerRequest;
use App\Http\Requests\Api\External\UploadPaymentReceiptRequest;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
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

    public function checkRegistration(CheckRegistrationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $turnamen = Turnamen::findOrFail($data['id_turnamen']);
        $pemain = $this->registrationService->findPemainByPhone($data['no_hp']);

        if (! $pemain) {
            return response()->json([
                'success' => true,
                'data' => [
                    'registered' => false,
                    'turnamen_id' => $turnamen->id,
                    'no_hp' => $data['no_hp'],
                    'pemain_exists' => false,
                    'pemain' => null,
                    'registration' => null,
                ],
            ]);
        }

        $peserta = $pemain->pesertaForTurnamen($turnamen);

        return response()->json([
            'success' => true,
            'data' => [
                'registered' => $peserta !== null,
                'turnamen_id' => $turnamen->id,
                'no_hp' => $pemain->no_hp,
                'pemain_exists' => true,
                'pemain' => [
                    'id' => $pemain->id,
                    'nama' => $pemain->nama,
                    'gender' => $pemain->gender,
                ],
                'registration' => $peserta ? [
                    'peserta_id' => $peserta->id,
                    'status' => $peserta->status,
                    'bukti_bayar_url' => $peserta->bukti_bayar_url,
                    'paired_at' => $peserta->paired_at
                        ? $peserta->paired_at->toDateTimeString()
                        : null,
                ] : null,
            ],
        ]);
    }

    public function uploadPaymentReceipt(UploadPaymentReceiptRequest $request): JsonResponse
    {
        $peserta = $this->resolvePesertaForPaymentReceipt($request);

        if (! $peserta) {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran turnamen tidak ditemukan.',
            ], 404);
        }

        try {
            $this->registrationService->updateBuktiBayar(
                $peserta,
                $request->file('bukti_bayar')
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $peserta->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Bukti bayar berhasil diunggah.',
            'data' => [
                'peserta_id' => $peserta->id,
                'turnamen_id' => $peserta->id_turnamen,
                'pemain_id' => $peserta->id_pemain1,
                'nama' => $peserta->display_name,
                'status' => $peserta->status,
                'bukti_bayar' => $peserta->bukti_bayar,
                'bukti_bayar_url' => $peserta->bukti_bayar_url,
            ],
        ]);
    }

    protected function resolvePesertaForPaymentReceipt(UploadPaymentReceiptRequest $request): ?TurnamenPeserta
    {
        if ($request->filled('peserta_id')) {
            return TurnamenPeserta::query()->find($request->peserta_id);
        }

        $pemain = Pemain::where('no_hp', $request->no_hp)->first();

        if (! $pemain) {
            return null;
        }

        return TurnamenPeserta::query()
            ->forTurnamen((int) $request->id_turnamen)
            ->involvingPemain($pemain->id)
            ->first();
    }
}
