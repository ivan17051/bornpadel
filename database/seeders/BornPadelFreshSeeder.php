<?php

namespace Database\Seeders;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BornPadelFreshSeeder extends Seeder
{
    public function run()
    {
        $this->truncateApplicationData();

        $singleTurnamen = $this->createTurnamen(
            'Born Padel Singles Cup 2026',
            'single',
            '2026-08-05',
            175000,
            'Turnamen single padel terbuka untuk semua level.'
        );

        $doubleTurnamen = $this->createTurnamen(
            'Born Padel Double Open 2026',
            'double',
            '2026-06-15',
            300000,
            'Turnamen double: setiap peserta mendaftar individu. Pasangan dibuat otomatis saat pendaftaran ditutup.'
        );

        $mahjongTurnamen = $this->createTurnamen(
            'Born Mahjong Championship 2026',
            'mahjong',
            '2026-07-10',
            200000,
            'Turnamen Mahjong poin akumulasi. Pemain terdaftar akan dibagi grup 4.'
        );

        $this->seedIndividualRegistrations($singleTurnamen, 'single', 20);
        $this->seedIndividualRegistrations($doubleTurnamen, 'double', 20);
        $this->seedIndividualRegistrations($mahjongTurnamen, 'mahjong', 20);

        $this->command->info('Fresh seed completed (m_users preserved).');
        $this->command->info("  - Single: {$singleTurnamen->nama} — 20 pemain");
        $this->command->info("  - Double: {$doubleTurnamen->nama} — 20 pemain (individu, belum dipasangkan)");
        $this->command->info("  - Mahjong: {$mahjongTurnamen->nama} — 20 pemain");
    }

    protected function truncateApplicationData(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('m_users')->update(['id_turnamen' => null]);

        $tables = [
            'pertandingan_skor',
            'pertandingan',
            'grup_member',
            'turnamen_pemenang',
            'grup',
            'turnamen_peserta',
            'm_pemain',
            'm_turnamen',
            'personal_access_tokens',
            'password_resets',
            'failed_jobs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->command->warn('Application data truncated (m_users kept).');
    }

    protected function createTurnamen(
        string $nama,
        string $jenis,
        string $tanggal,
        int $harga,
        string $syarat
    ): Turnamen {
        return Turnamen::create([
            'nama' => $nama,
            'tanggal' => $tanggal,
            'harga' => $harga,
            'syarat' => $syarat,
            'jenis' => $jenis,
            'status' => 'open',
            'mahjong_is_final' => false,
            'registration_paired_at' => null,
        ]);
    }

    protected function seedIndividualRegistrations(Turnamen $turnamen, string $prefix, int $count): void
    {
        $label = ucfirst($prefix);

        for ($i = 1; $i <= $count; $i++) {
            $pemain = $this->createPemain(
                "{$label} Pemain " . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                $this->phoneFor($prefix, $i),
                $i % 2 === 1 ? 'male' : 'female',
                $this->ratingFor($i)
            );

            $this->registerIndividual($turnamen, $pemain, $this->registrationStatus($i));
        }
    }

    protected function createPemain(string $nama, string $noHp, string $gender, float $rating): Pemain
    {
        $birthYear = 1988 + (crc32($noHp) % 15);
        $tglLahir = Carbon::create($birthYear, (crc32($nama) % 12) + 1, (crc32($nama) % 27) + 1);

        return Pemain::create([
            'nama' => $nama,
            'tgl_lahir' => $tglLahir,
            'usia' => $tglLahir->age,
            'gender' => $gender,
            'no_hp' => $noHp,
            'rating' => $rating,
            'total_poin' => 0,
        ]);
    }

    /**
     * Individual registration — one turnamen_peserta row per player (current app logic).
     */
    protected function registerIndividual(Turnamen $turnamen, Pemain $pemain, string $status): void
    {
        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'id_pemain2' => null,
            'status' => $status,
            'bukti_bayar' => null,
            'paired_at' => null,
        ]);
    }

    protected function phoneFor(string $type, int $index): string
    {
        $prefix = [
            'single' => '0823',
            'double' => '0821',
            'mahjong' => '0822',
        ][$type] ?? '0829';

        return $prefix . str_pad((string) $index, 8, '0', STR_PAD_LEFT);
    }

    protected function ratingFor(int $index): float
    {
        $base = 2.5 + (($index * 7) % 26) / 10;

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

        if ($mod === 2) {
            return 'unpaid';
        }

        return 'approved';
    }
}
