<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFriendlyJenisToMTurnamenTable extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE m_turnamen MODIFY jenis ENUM('single', 'double', 'mahjong', 'friendly') NOT NULL DEFAULT 'single'");
    }

    public function down()
    {
        DB::table('m_turnamen')->where('jenis', 'friendly')->update(['jenis' => 'single']);
        DB::statement("ALTER TABLE m_turnamen MODIFY jenis ENUM('single', 'double', 'mahjong') NOT NULL DEFAULT 'single'");
    }
}
