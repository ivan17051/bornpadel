<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDoublePairingColumns extends Migration
{
    public function up()
    {
        Schema::table('turnamen_peserta', function (Blueprint $table) {
            $table->timestamp('paired_at')->nullable()->after('bukti_bayar');
        });

        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->timestamp('registration_paired_at')->nullable()->after('mahjong_is_final');
        });
    }

    public function down()
    {
        Schema::table('turnamen_peserta', function (Blueprint $table) {
            $table->dropColumn('paired_at');
        });

        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->dropColumn('registration_paired_at');
        });
    }
}
