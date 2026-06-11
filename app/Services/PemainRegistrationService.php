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

    public function __construct(PemainPhotoService $photoService)
    {
        $this->photoService = $photoService;
    }

    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::open()->latest('doc')->first();
    }

    public function getOpenTournaments(): Collection
    {
        return Turnamen::open()->latest('doc')->get();
    }

    public function findPemainByPhone(string $noHp): ?Pemain
    {
        $trimmed = trim($noHp);

        return Pemain::where('no_hp', $trimmed)->first();
    }

    public function isRegisteredForTournament(Pemain $pemain, Turnamen $turnamen): bool
    {
        return TurnamenPeserta::where('id_turnamen', $turnamen->id)
            ->where('id_pemain', $pemain->id)
            ->exists();
    }

    public function register(Turnamen $turnamen, array $data, ?UploadedFile $foto = null): Pemain
    {
        $existing = $this->findPemainByPhone($data['no_hp']);

        if ($existing) {
            if ($this->isRegisteredForTournament($existing, $turnamen)) {
                throw new RuntimeException('Nomor HP sudah terdaftar pada turnamen ini.');
            }

            $updatePayload = [
                'nama' => $data['nama'],
                'tgl_lahir' => $data['tgl_lahir'],
                'usia' => Carbon::parse($data['tgl_lahir'])->age,
                'gender' => $data['gender'],
                'rating' => $data['rating'] ?? 0,
            ];

            if ($foto) {
                $this->photoService->delete($existing->foto);
                $updatePayload['foto'] = $this->photoService->storeAsWebp($foto);
            }

            $existing->update($updatePayload);

            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain' => $existing->id,
                'status' => 'pending',
            ]);

            return $existing->fresh();
        }

        $fotoPath = $foto ? $this->photoService->storeAsWebp($foto) : null;

        $pemain = Pemain::create([
            'nama' => $data['nama'],
            'tgl_lahir' => $data['tgl_lahir'],
            'usia' => Carbon::parse($data['tgl_lahir'])->age,
            'gender' => $data['gender'],
            'no_hp' => trim($data['no_hp']),
            'rating' => $data['rating'] ?? 0,
            'foto' => $fotoPath,
        ]);

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain' => $pemain->id,
            'status' => 'pending',
        ]);

        return $pemain;
    }

    public function getRegistrationStatus(Pemain $pemain, Turnamen $turnamen): ?string
    {
        return optional($pemain->pesertaForTurnamen($turnamen))->status;
    }
}
