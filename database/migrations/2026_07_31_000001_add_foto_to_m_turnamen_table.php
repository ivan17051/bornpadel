<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFotoToMTurnamenTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('m_turnamen')) {
            return;
        }

        if (! Schema::hasColumn('m_turnamen', 'foto')) {
            Schema::table('m_turnamen', function (Blueprint $table) {
                $table->string('foto')->nullable()->after('syarat');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('m_turnamen')) {
            return;
        }

        if (Schema::hasColumn('m_turnamen', 'foto')) {
            Schema::table('m_turnamen', function (Blueprint $table) {
                $table->dropColumn('foto');
            });
        }
    }
}
