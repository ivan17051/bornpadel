<?php

namespace Database\Seeders;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Database\Seeder;

class PemainSeeder extends Seeder
{
    public function run()
    {
        $turnamen = Turnamen::query()->orderByDesc('doc')->first();

        $pemain = [
            ['nama' => 'Andi Wijaya', 'tgl_lahir' => '1995-03-12', 'usia' => 31, 'gender' => 'male', 'no_hp' => '081234567801', 'rating' => 4.50, 'status' => 'approved'],
            ['nama' => 'Budi Santoso', 'tgl_lahir' => '1992-07-25', 'usia' => 33, 'gender' => 'male', 'no_hp' => '081234567802', 'rating' => 4.20, 'status' => 'approved'],
            ['nama' => 'Citra Dewi', 'tgl_lahir' => '1998-11-08', 'usia' => 27, 'gender' => 'female', 'no_hp' => '081234567803', 'rating' => 3.80, 'status' => 'approved'],
            ['nama' => 'Dian Pratama', 'tgl_lahir' => '1990-01-30', 'usia' => 36, 'gender' => 'male', 'no_hp' => '081234567804', 'rating' => 5.00, 'status' => 'approved'],
            ['nama' => 'Eka Putri', 'tgl_lahir' => '1999-05-17', 'usia' => 27, 'gender' => 'female', 'no_hp' => '081234567805', 'rating' => 3.50, 'status' => 'approved'],
            ['nama' => 'Fajar Nugroho', 'tgl_lahir' => '1994-09-03', 'usia' => 31, 'gender' => 'male', 'no_hp' => '081234567806', 'rating' => 4.00, 'status' => 'approved'],
            ['nama' => 'Gita Rahayu', 'tgl_lahir' => '2000-12-22', 'usia' => 25, 'gender' => 'female', 'no_hp' => '081234567807', 'rating' => 3.20, 'status' => 'pending'],
            ['nama' => 'Hendra Kusuma', 'tgl_lahir' => '1988-06-14', 'usia' => 37, 'gender' => 'male', 'no_hp' => '081234567808', 'rating' => 4.80, 'status' => 'approved'],
        ];

        foreach ($pemain as $data) {
            $status = $data['status'];
            unset($data['status']);

            $record = Pemain::create($data);

            if ($turnamen) {
                TurnamenPeserta::create([
                    'id_turnamen' => $turnamen->id,
                    'id_pemain1' => $record->id,
                    'status' => $status,
                ]);
            }
        }
    }
}
