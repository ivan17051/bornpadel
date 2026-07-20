<?php

use App\Models\Turnamen;
use App\Services\MatchScoringService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConvertGamesMenangToSignedGameDifference extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('grup_member') || ! Schema::hasColumn('grup_member', 'games_menang')) {
            return;
        }

        DB::statement('ALTER TABLE grup_member MODIFY games_menang INT NOT NULL DEFAULT 0');

        $scoring = app(MatchScoringService::class);

        Turnamen::query()
            ->where('jenis', '!=', 'mahjong')
            ->whereHas('grup')
            ->orderBy('id')
            ->each(function (Turnamen $turnamen) use ($scoring) {
                $scoring->recalculateGroupStandingsForTurnamen($turnamen);
            });
    }

    public function down()
    {
        if (! Schema::hasTable('grup_member') || ! Schema::hasColumn('grup_member', 'games_menang')) {
            return;
        }

        DB::table('grup_member')
            ->where('games_menang', '<', 0)
            ->update(['games_menang' => 0]);

        DB::statement('ALTER TABLE grup_member MODIFY games_menang INT UNSIGNED NOT NULL DEFAULT 0');
    }
}
