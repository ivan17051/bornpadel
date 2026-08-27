<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsWinnerToMahjongPoinEntryTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('mahjong_poin_entry')) {
            return;
        }

        if (! Schema::hasColumn('mahjong_poin_entry', 'is_winner')) {
            Schema::table('mahjong_poin_entry', function (Blueprint $table) {
                $table->boolean('is_winner')->default(false)->after('poin');
                $table->index('is_winner');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('mahjong_poin_entry')) {
            return;
        }

        if (Schema::hasColumn('mahjong_poin_entry', 'is_winner')) {
            Schema::table('mahjong_poin_entry', function (Blueprint $table) {
                $table->dropIndex(['is_winner']);
                $table->dropColumn('is_winner');
            });
        }
    }
}
