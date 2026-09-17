<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMahjongTeamJenisToMTurnamenTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('m_turnamen')) {
            return;
        }

        $column = DB::select("SHOW COLUMNS FROM m_turnamen LIKE 'jenis'");
        $type = strtolower((string) optional($column[0] ?? null)->Type);

        if (str_contains($type, "'mahjong_team'")) {
            return;
        }

        DB::statement("ALTER TABLE m_turnamen MODIFY jenis ENUM('single', 'double', 'mahjong', 'friendly', 'mahjong_team') NOT NULL DEFAULT 'single'");
    }

    public function down()
    {
        DB::table('m_turnamen')->where('jenis', 'mahjong_team')->update(['jenis' => 'mahjong']);
        DB::statement("ALTER TABLE m_turnamen MODIFY jenis ENUM('single', 'double', 'mahjong', 'friendly') NOT NULL DEFAULT 'single'");
    }
}
