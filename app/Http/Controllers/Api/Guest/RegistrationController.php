<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPublicKategori;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Models\TurnamenPeserta;
use App\Services\PemainRegistrationService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RegistrationController extends Controller
{
    use ResolvesPublicKategori;

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

        try {
            $kategori = $this->resolveApiKategori(
                $turnamen,
                $request->filled('id_kategori') ? $request->input('id_kategori') : null
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (! $kategori->isRegistrationOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran untuk kategori ini sudah ditutup.',
            ], 422);
        }

        $buktiBayar = $request->file('bukti_bayar');
        $kategoriId = $kategori->id;

        try {
            if ($turnamen->allowsGroupRegistration() && $request->isGroupRegistration()) {
                $result = $this->registrationService->registerGroup(
                    $turnamen,
                    (string) $request->input('nama_grup'),
                    $request->groupPlayersPayload(),
                    $request->groupFotosPayload(),
                    $buktiBayar,
                    TurnamenPeserta::SUMBER_INTERNAL,
                    false,
                    null,
                    null,
                    $kategoriId
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
                    false,
                    null,
                    null,
                    $kategoriId
                );

                $pemain = $pair['pemain'];
                $partner = $pair['partner'];
                $peserta = TurnamenPeserta::query()
                    ->forKategori($kategoriId)
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
                    false,
                    $kategoriId
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
                    'status' => $this->registrationService->getRegistrationStatus($pemain, $turnamen, $kategoriId),
                ],
                'partner' => $partner ? [
                    'id' => $partner->id,
                    'nama' => $partner->nama,
                    'no_hp' => $partner->no_hp,
                    'status' => $this->registrationService->getRegistrationStatus($partner, $turnamen, $kategoriId),
                ] : null,
                'players' => $players->map(function ($player) use ($turnamen, $kategoriId) {
                    return [
                        'id' => $player->id,
                        'nama' => $player->nama,
                        'no_hp' => $player->no_hp,
                        'status' => $this->registrationService->getRegistrationStatus($player, $turnamen, $kategoriId),
                    ];
                })->values()->all(),
                'pasangan_id' => optional($pasangan)->id,
                'turnamen' => [
                    'id' => $turnamen->id,
                    'nama' => $turnamen->nama,
                    'jenis' => $turnamen->jenis,
                ],
                'kategori' => [
                    'id' => $kategori->id,
                    'nama' => $kategori->nama,
                ],
            ],
        ], 201);
    }
}
