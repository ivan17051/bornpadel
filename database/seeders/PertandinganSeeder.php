<?php

namespace Database\Seeders;

use App\Models\Pertandingan;
use Illuminate\Database\Seeder;

class PertandinganSeeder extends Seeder
{
    public function run()
    {
        // Fase Grup - Grup A
        Pertandingan::create([
            'id_turnamen' => 1,
            'id_grup' => 1,
            'nama_ronde' => 'Fase Grup',
            'id_pemain1' => 1,
            'id_pemain2' => 2,
            'id_pemenang' => 1,
            'status' => 'completed',
        ]);

        Pertandingan::create([
            'id_turnamen' => 1,
            'id_grup' => 1,
            'nama_ronde' => 'Fase Grup',
            'id_pemain1' => 3,
            'id_pemain2' => 4,
            'id_pemenang' => 3,
            'status' => 'completed',
        ]);

        // Fase Grup - Grup B
        Pertandingan::create([
            'id_turnamen' => 1,
            'id_grup' => 2,
            'nama_ronde' => 'Fase Grup',
            'id_pemain1' => 5,
            'id_pemain2' => 6,
            'id_pemenang' => 5,
            'status' => 'completed',
        ]);

        // Semifinal (knockout - no grup)
        $semi1 = Pertandingan::create([
            'id_turnamen' => 1,
            'id_grup' => null,
            'nama_ronde' => 'Semifinal',
            'id_pemain1' => 1,
            'id_pemain2' => 5,
            'id_pemenang' => 1,
            'status' => 'completed',
        ]);

        $semi2 = Pertandingan::create([
            'id_turnamen' => 1,
            'id_grup' => null,
            'nama_ronde' => 'Semifinal',
            'id_pemain1' => 3,
            'id_pemain2' => 6,
            'id_pemenang' => 3,
            'status' => 'completed',
        ]);

        // Final
        Pertandingan::create([
            'id_turnamen' => 1,
            'id_grup' => null,
            'nama_ronde' => 'Final',
            'id_pemain1' => 1,
            'id_pemain2' => 3,
            'id_pemenang' => null,
            'status' => 'scheduled',
            'id_next_pertandingan' => null,
        ]);

        // Link semifinal winners to final
        $final = Pertandingan::find(6);
        $semi1->update(['id_next_pertandingan' => $final->id]);
        $semi2->update(['id_next_pertandingan' => $final->id]);
    }
}
