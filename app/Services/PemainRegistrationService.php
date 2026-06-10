<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Turnamen;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PemainRegistrationService
{
    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::open()->latest('doc')->first();
    }

    public function getOpenTournaments(): Collection
    {
        return Turnamen::open()->latest('doc')->get();
    }

    public function register(array $data): Pemain
    {
        return Pemain::create([
            'nama' => $data['nama'],
            'tgl_lahir' => $data['tgl_lahir'],
            'usia' => Carbon::parse($data['tgl_lahir'])->age,
            'gender' => $data['gender'],
            'no_hp' => $data['no_hp'],
            'rating' => $data['rating'] ?? 0,
            'status' => 'pending',
        ]);
    }
}
