<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMahjongExternalScoringEnabledFlag extends Migration
{
    public function up()
    {
        if (Schema::hasTable('m_turnamen') && ! Schema::hasColumn('m_turnamen', 'mahjong_external_scoring_enabled')) {
            Schema::table('m_turnamen', function (Blueprint $table) {
                $table->boolean('mahjong_external_scoring_enabled')->default(true)->after('mahjong_is_final');
            });
        }

        if (Schema::hasTable('turnamen_kategori') && ! Schema::hasColumn('turnamen_kategori', 'mahjong_external_scoring_enabled')) {
            Schema::table('turnamen_kategori', function (Blueprint $table) {
                $table->boolean('mahjong_external_scoring_enabled')->default(true)->after('mahjong_is_final');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('turnamen_kategori') && Schema::hasColumn('turnamen_kategori', 'mahjong_external_scoring_enabled')) {
            Schema::table('turnamen_kategori', function (Blueprint $table) {
                $table->dropColumn('mahjong_external_scoring_enabled');
            });
        }

        if (Schema::hasTable('m_turnamen') && Schema::hasColumn('m_turnamen', 'mahjong_external_scoring_enabled')) {
            Schema::table('m_turnamen', function (Blueprint $table) {
                $table->dropColumn('mahjong_external_scoring_enabled');
            });
        }
    }
}
