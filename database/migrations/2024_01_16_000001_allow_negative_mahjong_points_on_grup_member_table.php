<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllowNegativeMahjongPointsOnGrupMemberTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE grup_member MODIFY poin_didapat INT NOT NULL DEFAULT 0');

        if (Schema::hasColumn('grup_member', 'poin_akumulasi')) {
            DB::statement('ALTER TABLE grup_member MODIFY poin_akumulasi INT NOT NULL DEFAULT 0');
        }
    }

    public function down()
    {
        DB::statement('ALTER TABLE grup_member MODIFY poin_didapat INT UNSIGNED NOT NULL DEFAULT 0');

        if (Schema::hasColumn('grup_member', 'poin_akumulasi')) {
            DB::statement('ALTER TABLE grup_member MODIFY poin_akumulasi INT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
}
