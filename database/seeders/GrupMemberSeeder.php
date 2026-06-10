<?php

namespace Database\Seeders;

use App\Models\GrupMember;
use Illuminate\Database\Seeder;

class GrupMemberSeeder extends Seeder
{
    public function run()
    {
        // Grup A: pemain 1-4
        GrupMember::create(['id_grup' => 1, 'id_pemain' => 1, 'poin_didapat' => 6, 'set_menang' => 4, 'games_menang' => 28]);
        GrupMember::create(['id_grup' => 1, 'id_pemain' => 2, 'poin_didapat' => 4, 'set_menang' => 3, 'games_menang' => 22]);
        GrupMember::create(['id_grup' => 1, 'id_pemain' => 3, 'poin_didapat' => 2, 'set_menang' => 1, 'games_menang' => 15]);
        GrupMember::create(['id_grup' => 1, 'id_pemain' => 4, 'poin_didapat' => 0, 'set_menang' => 0, 'games_menang' => 8]);

        // Grup B: pemain 5-8
        GrupMember::create(['id_grup' => 2, 'id_pemain' => 5, 'poin_didapat' => 6, 'set_menang' => 4, 'games_menang' => 26]);
        GrupMember::create(['id_grup' => 2, 'id_pemain' => 6, 'poin_didapat' => 4, 'set_menang' => 2, 'games_menang' => 20]);
        GrupMember::create(['id_grup' => 2, 'id_pemain' => 7, 'poin_didapat' => 2, 'set_menang' => 1, 'games_menang' => 14]);
        GrupMember::create(['id_grup' => 2, 'id_pemain' => 8, 'poin_didapat' => 0, 'set_menang' => 0, 'games_menang' => 10]);
    }
}
