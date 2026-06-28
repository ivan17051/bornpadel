<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddBabak16ToPertandinganNamaRonde extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('pertandingan')) {
            return;
        }

        DB::statement(
            "ALTER TABLE pertandingan MODIFY nama_ronde ENUM('Fase Grup', 'Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final') NOT NULL"
        );
    }

    public function down()
    {
        if (! Schema::hasTable('pertandingan')) {
            return;
        }

        DB::statement(
            "UPDATE pertandingan SET nama_ronde = 'Perempatfinal' WHERE nama_ronde = 'Babak 16 Besar'"
        );

        DB::statement(
            "ALTER TABLE pertandingan MODIFY nama_ronde ENUM('Fase Grup', 'Perempatfinal', 'Semifinal', 'Final') NOT NULL"
        );
    }
}
