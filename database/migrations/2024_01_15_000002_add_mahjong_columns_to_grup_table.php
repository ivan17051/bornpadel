<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMahjongColumnsToGrupTable extends Migration
{
    public function up()
    {
        Schema::table('grup', function (Blueprint $table) {
            if (! Schema::hasColumn('grup', 'babak')) {
                $table->unsignedSmallInteger('babak')->default(1)->after('nama');
            }
            if (! Schema::hasColumn('grup', 'is_aktif')) {
                $table->boolean('is_aktif')->default(true)->after('babak');
            }
        });

        Schema::table('m_turnamen', function (Blueprint $table) {
            if (! Schema::hasColumn('m_turnamen', 'mahjong_is_final')) {
                $table->boolean('mahjong_is_final')->default(false)->after('status');
            }
        });
    }

    public function down()
    {
        Schema::table('grup', function (Blueprint $table) {
            if (Schema::hasColumn('grup', 'babak')) {
                $table->dropColumn('babak');
            }
            if (Schema::hasColumn('grup', 'is_aktif')) {
                $table->dropColumn('is_aktif');
            }
        });

        Schema::table('m_turnamen', function (Blueprint $table) {
            if (Schema::hasColumn('m_turnamen', 'mahjong_is_final')) {
                $table->dropColumn('mahjong_is_final');
            }
        });
    }
}
