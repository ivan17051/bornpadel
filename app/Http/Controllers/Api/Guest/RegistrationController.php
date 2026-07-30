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
            if ($turnamen->allowsGroupRegistration() && $request->isGroupRegistration()) {
                $result = $this->registrationService->registerGroup(
                    $turnamen,
                    (string) $request->input('nama_grup'),
                    $request->groupPlayersPayload(),
                    [
                        $request->file('foto'),
                        $request->file('foto_2'),
                        $request->file('foto_3'),
                        $request->file('foto_4'),
                    ],
                    $buktiBayar,
                    TurnamenPeserta::SUMBER_INTERNAL,
                    false
                );

                $players = $result['players'];
                $pemain = $players[0];
                $partner = null;
                $pasangan = null;
                $grupPendaftaran = $result['grup_pendaftaran'];
                $registrationType = 'group';
            } elseif ($turnamen->requiresPairRegistration() && $request->isPairRegistration()) {
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
                $grupPendaftaran = null;
                $registrationType = 'pair';
                $players = collect([$pemain, $partner]);
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
                $grupPendaftaran = null;
                $registrationType = 'single';
                $players = collect([$pemain]);
            }
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($registrationType === 'group') {
            $message = 'Pendaftaran grup berhasil! Tim kami akan memverifikasi data Anda.';
        } elseif ($registrationType === 'pair') {
            $message = 'Pendaftaran berpasangan berhasil! Tim kami akan memverifikasi data Anda.';
        } else {
            $message = 'Pendaftaran berhasil! Tim kami akan memverifikasi data Anda.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'registration_type' => $registrationType,
                'nama_grup' => optional($grupPendaftaran)->nama,
                'grup_pendaftaran_id' => optional($grupPendaftaran)->id,
                'pemain' => [
                    'id' => $pemain->id,
                    'nama' => $pemain->nama,
                    'no_hp' => $pemain->no_hp,
                    'status' => $this->registrationService->getRegistrationStatus($pemain, $turnamen),
                ],
                'partner' => $partner ? [
                    'id' => $partner->id,
                    'nama' => $partner->nama,
                    'no_hp' => $partner->no_hp,
                    'status' => $this->registrationService->getRegistrationStatus($partner, $turnamen),
                ] : null,
                'players' => $players->map(function ($player) use ($turnamen) {
                    return [
                        'id' => $player->id,
                        'nama' => $player->nama,
                        'no_hp' => $player->no_hp,
                        'status' => $this->registrationService->getRegistrationStatus($player, $turnamen),
                    ];
                })->values()->all(),
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
