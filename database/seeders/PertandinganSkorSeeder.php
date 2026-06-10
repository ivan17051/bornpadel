<?php

namespace Database\Seeders;

use App\Models\PertandinganSkor;
use Illuminate\Database\Seeder;

class PertandinganSkorSeeder extends Seeder
{
    public function run()
    {
        // Pertandingan 1: Andi vs Budi (2-1 sets)
        PertandinganSkor::create(['id_pertandingan' => 1, 'set_ke' => 1, 'skor_pemain1' => 6, 'skor_pemain2' => 4]);
        PertandinganSkor::create(['id_pertandingan' => 1, 'set_ke' => 2, 'skor_pemain1' => 4, 'skor_pemain2' => 6]);
        PertandinganSkor::create(['id_pertandingan' => 1, 'set_ke' => 3, 'skor_pemain1' => 6, 'skor_pemain2' => 3]);

        // Pertandingan 2: Citra vs Dian (2-0 sets)
        PertandinganSkor::create(['id_pertandingan' => 2, 'set_ke' => 1, 'skor_pemain1' => 6, 'skor_pemain2' => 2]);
        PertandinganSkor::create(['id_pertandingan' => 2, 'set_ke' => 2, 'skor_pemain1' => 6, 'skor_pemain2' => 1]);

        // Pertandingan 3: Eka vs Fajar (2-1 sets)
        PertandinganSkor::create(['id_pertandingan' => 3, 'set_ke' => 1, 'skor_pemain1' => 6, 'skor_pemain2' => 3]);
        PertandinganSkor::create(['id_pertandingan' => 3, 'set_ke' => 2, 'skor_pemain1' => 3, 'skor_pemain2' => 6]);
        PertandinganSkor::create(['id_pertandingan' => 3, 'set_ke' => 3, 'skor_pemain1' => 7, 'skor_pemain2' => 5]);

        // Pertandingan 4: Semifinal Andi vs Eka (2-0)
        PertandinganSkor::create(['id_pertandingan' => 4, 'set_ke' => 1, 'skor_pemain1' => 6, 'skor_pemain2' => 4]);
        PertandinganSkor::create(['id_pertandingan' => 4, 'set_ke' => 2, 'skor_pemain1' => 6, 'skor_pemain2' => 2]);

        // Pertandingan 5: Semifinal Citra vs Fajar (2-1)
        PertandinganSkor::create(['id_pertandingan' => 5, 'set_ke' => 1, 'skor_pemain1' => 4, 'skor_pemain2' => 6]);
        PertandinganSkor::create(['id_pertandingan' => 5, 'set_ke' => 2, 'skor_pemain1' => 6, 'skor_pemain2' => 3]);
        PertandinganSkor::create(['id_pertandingan' => 5, 'set_ke' => 3, 'skor_pemain1' => 6, 'skor_pemain2' => 4]);
    }
}
