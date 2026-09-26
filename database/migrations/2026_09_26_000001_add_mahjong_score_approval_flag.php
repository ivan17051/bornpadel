<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMahjongScoreApprovalFlag extends Migration
{
    public function up()
    {
        if (Schema::hasTable('m_turnamen') && ! Schema::hasColumn('m_turnamen', 'mahjong_require_score_approval')) {
            Schema::table('m_turnamen', function (Blueprint $table) {
                $table->boolean('mahjong_require_score_approval')->default(false)->after('mahjong_external_scoring_enabled');
            });
        }

        if (Schema::hasTable('turnamen_kategori') && ! Schema::hasColumn('turnamen_kategori', 'mahjong_require_score_approval')) {
            Schema::table('turnamen_kategori', function (Blueprint $table) {
                $table->boolean('mahjong_require_score_approval')->default(false)->after('mahjong_external_scoring_enabled');
            });
        }

        if (Schema::hasTable('grup_member') && ! Schema::hasColumn('grup_member', 'poin_disetujui')) {
            Schema::table('grup_member', function (Blueprint $table) {
                $table->boolean('poin_disetujui')->default(false)->after('poin_penyesuaian');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('grup_member') && Schema::hasColumn('grup_member', 'poin_disetujui')) {
            Schema::table('grup_member', function (Blueprint $table) {
                $table->dropColumn('poin_disetujui');
            });
        }

        if (Schema::hasTable('turnamen_kategori') && Schema::hasColumn('turnamen_kategori', 'mahjong_require_score_approval')) {
            Schema::table('turnamen_kategori', function (Blueprint $table) {
                $table->dropColumn('mahjong_require_score_approval');
            });
        }

        if (Schema::hasTable('m_turnamen') && Schema::hasColumn('m_turnamen', 'mahjong_require_score_approval')) {
            Schema::table('m_turnamen', function (Blueprint $table) {
                $table->dropColumn('mahjong_require_score_approval');
            });
        }
    }
}
