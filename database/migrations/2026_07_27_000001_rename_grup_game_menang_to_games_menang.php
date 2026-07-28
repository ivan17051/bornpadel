<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenameGrupGameMenangToGamesMenang extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('grup')) {
            return;
        }

        // App + grup_member use games_menang; Friendly migration previously created game_menang.
        if (Schema::hasColumn('grup', 'game_menang') && ! Schema::hasColumn('grup', 'games_menang')) {
            DB::statement('ALTER TABLE grup CHANGE game_menang games_menang INT NOT NULL DEFAULT 0');
        } elseif (! Schema::hasColumn('grup', 'games_menang')) {
            Schema::table('grup', function ($table) {
                $table->integer('games_menang')->default(0)->after('set_menang');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('grup')) {
            return;
        }

        if (Schema::hasColumn('grup', 'games_menang') && ! Schema::hasColumn('grup', 'game_menang')) {
            DB::statement('ALTER TABLE grup CHANGE games_menang game_menang INT NOT NULL DEFAULT 0');
        }
    }
}
