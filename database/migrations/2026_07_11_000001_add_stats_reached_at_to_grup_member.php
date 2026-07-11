<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddStatsReachedAtToGrupMember extends Migration
{
    public function up()
    {
        Schema::table('grup_member', function (Blueprint $table) {
            $table->timestamp('stats_reached_at')->nullable()->after('games_menang');
        });

        DB::table('grup_member')
            ->whereNull('stats_reached_at')
            ->where(function ($query) {
                $query->where('poin_didapat', '!=', 0)
                    ->orWhere('set_menang', '!=', 0)
                    ->orWhere('games_menang', '!=', 0);
            })
            ->update(['stats_reached_at' => DB::raw('updated_at')]);
    }

    public function down()
    {
        Schema::table('grup_member', function (Blueprint $table) {
            $table->dropColumn('stats_reached_at');
        });
    }
}
