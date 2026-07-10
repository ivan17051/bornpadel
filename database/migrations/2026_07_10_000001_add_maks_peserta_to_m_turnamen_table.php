<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMaksPesertaToMTurnamenTable extends Migration
{
    public function up()
    {
        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->unsignedInteger('maks_peserta')->nullable()->after('harga');
        });
    }

    public function down()
    {
        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->dropColumn('maks_peserta');
        });
    }
}
