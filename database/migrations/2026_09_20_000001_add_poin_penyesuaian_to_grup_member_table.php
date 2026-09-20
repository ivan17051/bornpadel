<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPoinPenyesuaianToGrupMemberTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('grup_member', 'poin_penyesuaian')) {
            return;
        }

        Schema::table('grup_member', function (Blueprint $table) {
            $table->integer('poin_penyesuaian')->default(0)->after('poin_akumulasi');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('grup_member', 'poin_penyesuaian')) {
            return;
        }

        Schema::table('grup_member', function (Blueprint $table) {
            $table->dropColumn('poin_penyesuaian');
        });
    }
}
