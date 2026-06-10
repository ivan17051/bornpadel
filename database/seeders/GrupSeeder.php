<?php

namespace Database\Seeders;

use App\Models\Grup;
use Illuminate\Database\Seeder;

class GrupSeeder extends Seeder
{
    public function run()
    {
        Grup::create(['id_turnamen' => 1, 'nama' => 'Grup A']);
        Grup::create(['id_turnamen' => 1, 'nama' => 'Grup B']);
    }
}
