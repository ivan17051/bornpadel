<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJenisToMTurnamenTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('m_turnamen', 'jenis')) {
            return;
        }

        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->enum('jenis', ['single', 'double'])->default('single')->after('syarat');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('m_turnamen', 'jenis')) {
            return;
        }

        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
}
