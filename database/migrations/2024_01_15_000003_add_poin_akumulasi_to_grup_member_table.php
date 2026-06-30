<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPoinAkumulasiToGrupMemberTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('grup_member', 'poin_akumulasi')) {
            return;
        }

        Schema::table('grup_member', function (Blueprint $table) {
            $table->unsignedInteger('poin_akumulasi')->default(0)->after('poin_didapat');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('grup_member', 'poin_akumulasi')) {
            return;
        }

        Schema::table('grup_member', function (Blueprint $table) {
            $table->dropColumn('poin_akumulasi');
        });
    }
}
