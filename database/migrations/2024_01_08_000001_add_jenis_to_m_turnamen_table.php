<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJenisToMTurnamenTable extends Migration
{
    public function up()
    {
        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->enum('jenis', ['single', 'double'])->default('single')->after('syarat');
        });
    }

    public function down()
    {
        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
}
