<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTanggalToMTurnamenTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('m_turnamen', 'tanggal')) {
            return;
        }

        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('nama');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('m_turnamen', 'tanggal')) {
            return;
        }

        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });
    }
}
