<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddMahjongJenisToMTurnamenTable extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE m_turnamen MODIFY jenis ENUM('single', 'double', 'mahjong') NOT NULL DEFAULT 'single'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE m_turnamen MODIFY jenis ENUM('single', 'double') NOT NULL DEFAULT 'single'");
    }
}
