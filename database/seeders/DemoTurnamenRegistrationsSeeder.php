<?php

namespace Database\Seeders;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoTurnamenRegistrationsSeeder extends Seeder
{
    public function run()
    {
        $doubleTurnamen = Turnamen::updateOrCreate(
            ['nama' => 'Born Padel Double Open 2026'],
            [
                'tanggal' => '2026-06-15',
                'harga' => 300000,
                'syarat' => 'Terbuka untuk pasangan padel. Minimal rating 3.0 per pemain.',
                'jenis' => 'double',
                'status' => 'open',
            ]
        );

        $mahjongTurnamen = Turnamen::updateOrCreate(
            ['nama' => 'Born Mahjong Championship 2026'],
            [
                'tanggal' => '2026-07-10',
                'harga' => 200000,
                'syarat' => 'Turnamen Mahjong poin akumulasi. Pemain terdaftar akan dibagi grup 4.',
                'jenis' => 'mahjong',
                'status' => 'open',
            ]
        );

        $singleTurnamen = Turnamen::updateOrCreate(
            ['nama' => 'Born Padel Singles Cup 2026'],
            [
                'tanggal' => '2026-08-05',
                'harga' => 175000,
                'syarat' => 'Turnamen single padel terbuka untuk semua level.',
                'jenis' => 'single',
                'status' => 'open',
            ]
        );

        $this->seedDoublePairs($doubleTurnamen, 20);
        $this->seedSingleRegistrations($mahjongTurnamen, 20, 'mahjong');
        $this->seedSingleRegistrations($singleTurnamen, 20, 'single');

        $this->command->info('Seeded 3 turnamen:');
        $this->command->info("  - Double: {$doubleTurnamen->nama} (20 pasangan / 40 pemain)");
        $this->command->info("  - Mahjong: {$mahjongTurnamen->nama} (20 pemain)");
        $this->command->info("  - Single: {$singleTurnamen->nama} (20 pemain)");
    }

    protected function seedDoublePairs(Turnamen $turnamen, int $pairCount): void
    {
        for ($i = 1; $i <= $pairCount; $i++) {
            $phone1 = $this->phoneFor('double', $i, 1);
            $phone2 = $this->phoneFor('double', $i, 2);

            $pemain1 = $this->createPemain(
                "Double Pasangan {$i} — Pemain 1",
                $phone1,
                $i % 2 === 1 ? 'male' : 'female',
                $this->ratingFor($i, 1)
            );

            $pemain2 = $this->createPemain(
                "Double Pasangan {$i} — Pemain 2",
                $phone2,
                $i % 2 === 1 ? 'female' : 'male',
                $this->ratingFor($i, 2)
            );

            $this->registerPair($turnamen, $pemain1, $pemain2, $this->registrationStatus($i));
        }
    }

    protected function seedSingleRegistrations(Turnamen $turnamen, int $count, string $prefix): void
    {
        $label = $prefix === 'mahjong' ? 'Mahjong' : 'Single';

        for ($i = 1; $i <= $count; $i++) {
            $pemain = $this->createPemain(
                "{$label} Pemain " . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                $this->phoneFor($prefix, $i),
                $i % 2 === 1 ? 'male' : 'female',
                $this->ratingFor($i)
            );

            $this->registerSolo($turnamen, $pemain, $this->registrationStatus($i));
        }
    }

    protected function createPemain(string $nama, string $noHp, string $gender, float $rating): Pemain
    {
        $birthYear = 1988 + (crc32($noHp) % 15);
        $tglLahir = Carbon::create($birthYear, ($crc = crc32($nama)) % 12 + 1, ($crc % 27) + 1);

        return Pemain::updateOrCreate(
            ['no_hp' => $noHp],
            [
                'nama' => $nama,
                'tgl_lahir' => $tglLahir,
                'usia' => $tglLahir->age,
                'gender' => $gender,
                'rating' => $rating,
                'total_poin' => 0,
            ]
        );
    }

    protected function registerPair(Turnamen $turnamen, Pemain $pemain1, Pemain $pemain2, string $status): void
    {
        $peserta1 = TurnamenPeserta::updateOrCreate(
            [
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain1->id,
            ],
            [
                'status' => $status,
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]
        );

        $peserta2 = TurnamenPeserta::updateOrCreate(
            [
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain2->id,
            ],
            [
                'status' => $status,
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]
        );

        app(\App\Services\DoublePairingService::class)->createPair($turnamen, $peserta1, $peserta2);
    }

    protected function registerSolo(Turnamen $turnamen, Pemain $pemain, string $status): void
    {
        TurnamenPeserta::updateOrCreate(
            [
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
            ],
            [
                'status' => $status,
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]
        );
    }

    protected function phoneFor(string $type, int $index, int $slot = 1): string
    {
        if ($type === 'double') {
            $sequence = (($index - 1) * 2) + $slot;

            return '+62821' . str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
        }

        if ($type === 'mahjong') {
            return '+62822' . str_pad((string) $index, 8, '0', STR_PAD_LEFT);
        }

        return '+62823' . str_pad((string) $index, 8, '0', STR_PAD_LEFT);
    }

    protected function ratingFor(int $index, int $slot = 0): float
    {
        $base = 2.5 + (($index * 7 + $slot * 3) % 26) / 10;

        return round(min(5.0, max(2.0, $base)), 2);
    }

    protected function registrationStatus(int $index): string
    {
        $mod = $index % 10;

        if ($mod === 0) {
            return 'paid';
        }

        if ($mod === 1) {
            return 'pending';
        }

        return 'approved';
    }
}
