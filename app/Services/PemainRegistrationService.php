<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use RuntimeException;

class PemainRegistrationService
{
    protected $photoService;
    protected $paymentReceiptService;

    public function __construct(PemainPhotoService $photoService, PaymentReceiptService $paymentReceiptService)
    {
        $this->photoService = $photoService;
        $this->paymentReceiptService = $paymentReceiptService;
    }

    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::open()->latest('doc')->first();
    }

    public function resolveOpenTournament(?int $turnamenId = null): ?Turnamen
    {
        if ($turnamenId) {
            return Turnamen::open()->where('id', $turnamenId)->first();
        }

        return $this->getActiveTournament();
    }

    public function getOpenTournaments(): Collection
    {
        return Turnamen::open()->latest('doc')->get();
    }

    public function getPublicTournaments(): Collection
    {
        return Turnamen::publicVisible()->get();
    }

    public function resolvePublicTournament(?int $turnamenId = null): ?Turnamen
    {
        $tournaments = $this->getPublicTournaments();

        if ($turnamenId) {
            return $tournaments->firstWhere('id', $turnamenId);
        }

        return $tournaments->first();
    }

    public function findPemainByPhone(string $noHp): ?Pemain
    {
        $trimmed = trim($noHp);

        return Pemain::where('no_hp', $trimmed)->first();
    }

    public function isRegisteredForTournament(Pemain $pemain, Turnamen $turnamen): bool
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->involvingPemain($pemain->id)
            ->exists();
    }

    public function register(
        Turnamen $turnamen,
        array $data,
        ?UploadedFile $foto = null,
        ?UploadedFile $buktiBayar = null
    ): Pemain {
        $pemain = $this->upsertPemain($data, $foto);

        if ($this->isRegisteredForTournament($pemain, $turnamen)) {
            throw new RuntimeException('Nomor HP sudah terdaftar pada turnamen ini.');
        }

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => 'pending',
            'bukti_bayar' => $this->storeBuktiBayar($buktiBayar),
        ]);

        return $pemain->fresh();
    }

    /**
     * @return array{pemain: Pemain, partner: Pemain}
     */
    public function registerPair(
        Turnamen $turnamen,
        array $player1,
        ?UploadedFile $foto1,
        array $player2,
        ?UploadedFile $foto2,
        ?UploadedFile $buktiBayar = null
    ): array {
        if (trim($player1['no_hp']) === trim($player2['no_hp'])) {
            throw new RuntimeException('Nomor HP pemain 1 dan pemain 2 tidak boleh sama.');
        }

        $pemain = $this->upsertPemain($player1, $foto1);
        $partner = $this->upsertPemain($player2, $foto2);

        if ($this->isRegisteredForTournament($pemain, $turnamen)) {
            throw new RuntimeException('Nomor HP pemain 1 sudah terdaftar pada turnamen ini.');
        }

        if ($this->isRegisteredForTournament($partner, $turnamen)) {
            throw new RuntimeException('Nomor HP pemain 2 sudah terdaftar pada turnamen ini.');
        }

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'id_pemain2' => $partner->id,
            'status' => 'pending',
            'bukti_bayar' => $this->storeBuktiBayar($buktiBayar),
        ]);

        return [
            'pemain' => $pemain,
            'partner' => $partner,
        ];
    }

    public function upsertPemain(array $data, ?UploadedFile $foto = null): Pemain
    {
        $existing = $this->findPemainByPhone($data['no_hp']);

        if ($existing) {
            $updatePayload = array_merge([
                'nama' => $data['nama'],
                'gender' => $data['gender'],
                'rating' => $data['rating'] ?? 0,
            ], $this->resolveBirthFields($data));

            if ($foto) {
                $this->photoService->delete($existing->foto);
                $updatePayload['foto'] = $this->photoService->storeAsWebp($foto);
            }

            $existing->update($updatePayload);

            return $existing->fresh();
        }

        $fotoPath = $foto ? $this->photoService->storeAsWebp($foto) : null;

        return Pemain::create(array_merge([
            'nama' => $data['nama'],
            'gender' => $data['gender'],
            'no_hp' => trim($data['no_hp']),
            'rating' => $data['rating'] ?? 0,
            'foto' => $fotoPath,
        ], $this->resolveBirthFields($data)));
    }

    protected function resolveBirthFields(array $data): array
    {
        $tglLahir = $data['tgl_lahir'] ?? null;

        if (! empty($tglLahir)) {
            return [
                'tgl_lahir' => $tglLahir,
                'usia' => Carbon::parse($tglLahir)->age,
            ];
        }

        return [
            'tgl_lahir' => null,
            'usia' => null,
        ];
    }

    public function getRegistrationStatus(Pemain $pemain, Turnamen $turnamen): ?string
    {
        return optional($pemain->pesertaForTurnamen($turnamen))->status;
    }

    public function detachPemainFromDoublePeserta(TurnamenPeserta $peserta, int $pemainId): void
    {
        $updates = [];

        if ($peserta->id_pemain1 && (int) $peserta->id_pemain1 === $pemainId) {
            $updates['id_pemain1'] = null;
        }

        if ($peserta->id_pemain2 && (int) $peserta->id_pemain2 === $pemainId) {
            $updates['id_pemain2'] = null;
        }

        if ($updates === []) {
            return;
        }

        $remainingPemain1 = array_key_exists('id_pemain1', $updates) ? null : $peserta->id_pemain1;
        $remainingPemain2 = array_key_exists('id_pemain2', $updates) ? null : $peserta->id_pemain2;

        if ($remainingPemain1 === null && $remainingPemain2 === null) {
            $peserta->delete();

            return;
        }

        $updates['status'] = 'pending';

        $peserta->update($updates);
    }

    public function storeBuktiBayar(?UploadedFile $buktiBayar): ?string
    {
        if (! $buktiBayar) {
            return null;
        }

        return $this->paymentReceiptService->store($buktiBayar);
    }

    public function updateBuktiBayar(TurnamenPeserta $peserta, ?UploadedFile $buktiBayar): void
    {
        if (! $buktiBayar) {
            return;
        }

        $this->paymentReceiptService->delete($peserta->bukti_bayar);
        $peserta->update(['bukti_bayar' => $this->paymentReceiptService->store($buktiBayar)]);
    }
}
