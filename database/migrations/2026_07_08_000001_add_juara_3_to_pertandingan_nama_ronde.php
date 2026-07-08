<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddJuara3ToPertandinganNamaRonde extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('pertandingan')) {
            return;
        }

        DB::statement(
            "ALTER TABLE pertandingan MODIFY nama_ronde ENUM('Fase Grup', 'Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final', 'Perebutan Juara 3') NOT NULL"
        );
    }

    public function down()
    {
        if (! Schema::hasTable('pertandingan')) {
            return;
        }

        DB::statement(
            "DELETE FROM pertandingan WHERE nama_ronde = 'Perebutan Juara 3'"
        );

        DB::statement(
            "ALTER TABLE pertandingan MODIFY nama_ronde ENUM('Fase Grup', 'Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final') NOT NULL"
        );
    }
}
