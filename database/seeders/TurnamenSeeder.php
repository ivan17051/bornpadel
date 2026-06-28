<?php

namespace Database\Seeders;

use App\Models\Turnamen;
use Illuminate\Database\Seeder;

class TurnamenSeeder extends Seeder
{
    public function run()
    {
        Turnamen::create([
            'nama' => 'Born Padel Open 2026',
            'tanggal' => '2026-03-15',
            'harga' => 250000,
            'syarat' => 'Minimal usia 18 tahun, rating WPT minimal 2.0, membawa raket sendiri.',
            'jenis' => 'double',
            'status' => 'open',
        ]);

        Turnamen::create([
            'nama' => 'Born Padel Club Championship',
            'tanggal' => '2026-04-20',
            'harga' => 150000,
            'syarat' => 'Terbuka untuk member aktif Born Padel Club.',
            'jenis' => 'single',
            'status' => 'draft',
        ]);
    }
}
