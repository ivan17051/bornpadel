<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenPasangan;
use App\Models\TurnamenPeserta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DoublePartnerManagementService
{
    protected $registrationService;
    protected $pairingService;

    public function __construct(
        PemainRegistrationService $registrationService,
        DoublePairingService $pairingService
    ) {
        $this->registrationService = $registrationService;
        $this->pairingService = $pairingService;
    }

    public function removePartner(TurnamenPeserta $peserta): TurnamenPeserta
    {
        $this->assertDoubleTournament($peserta);

        $pasangan = $peserta->pasangan;

        if (! $pasangan) {
            throw new RuntimeException('Peserta ini belum memiliki pasangan.');
        }

        $pasangan->delete();

        return $peserta->fresh();
    }

    /**
     * Pair with an existing solo peserta row.
     */
    public function pairWithPeserta(TurnamenPeserta $primary, TurnamenPeserta $partnerPeserta): TurnamenPasangan
    {
        $this->assertDoubleTournament($primary);
        $this->assertSameTurnamen($primary, $partnerPeserta);
        $this->unpairIfNeeded($primary);

        if ($partnerPeserta->isPaired()) {
            throw new RuntimeException('Peserta pasangan yang dipilih sudah memiliki pasangan lain.');
        }

        if ((int) $primary->id === (int) $partnerPeserta->id) {
            throw new RuntimeException('Peserta tidak dapat dipasangkan dengan dirinya sendiri.');
        }

        return $this->pairingService->createPair($primary->turnamen, $primary, $partnerPeserta);
    }

    /**
     * Create a new player + peserta row and pair with the primary peserta.
     */
    public function pairWithNewPlayer(
        TurnamenPeserta $primary,
        array $partnerData,
        ?UploadedFile $foto = null
    ): TurnamenPasangan {
        $this->assertDoubleTournament($primary);
        $this->unpairIfNeeded($primary);

        $turnamen = $primary->turnamen;
        $pemain = $this->registrationService->upsertPemain($partnerData, $foto);

        if ($this->registrationService->isRegisteredForTournament($pemain, $turnamen)) {
            throw new RuntimeException('Pemain pasangan sudah terdaftar pada turnamen ini.');
        }

        if ($primary->pemain1 && (int) $primary->pemain1->id === (int) $pemain->id) {
            throw new RuntimeException('Pemain pasangan harus berbeda dari pemain utama.');
        }

        return DB::transaction(function () use ($primary, $turnamen, $pemain) {
            $partnerPeserta = TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => $primary->status,
                'bukti_bayar' => $primary->bukti_bayar,
                'sumber' => $primary->sumber ?? TurnamenPeserta::SUMBER_INTERNAL,
            ]);

            return $this->pairingService->createPair($turnamen, $primary, $partnerPeserta);
        });
    }

    protected function assertDoubleTournament(TurnamenPeserta $peserta): void
    {
        $peserta->loadMissing('turnamen');

        if (! $peserta->turnamen || ! $peserta->turnamen->requiresPairRegistration()) {
            throw new RuntimeException('Fitur pasangan hanya tersedia untuk turnamen double.');
        }
    }

    protected function assertSameTurnamen(TurnamenPeserta $primary, TurnamenPeserta $partner): void
    {
        if ((int) $primary->id_turnamen !== (int) $partner->id_turnamen) {
            throw new RuntimeException('Kedua peserta harus berada pada turnamen yang sama.');
        }
    }

    protected function unpairIfNeeded(TurnamenPeserta $peserta): void
    {
        $pasangan = $peserta->pasangan;

        if ($pasangan) {
            $pasangan->delete();
        }
    }
}
