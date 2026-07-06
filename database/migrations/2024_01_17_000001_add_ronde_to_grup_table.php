<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRondeToGrupTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('grup')) {
            return;
        }

        if (! Schema::hasColumn('grup', 'ronde')) {
            Schema::table('grup', function (Blueprint $table) {
                $table->unsignedSmallInteger('ronde')->default(1)->after('babak');
            });
        }

        $this->backfillRondeFromCreatedAt();
    }

    public function down()
    {
        if (! Schema::hasTable('grup') || ! Schema::hasColumn('grup', 'ronde')) {
            return;
        }

        Schema::table('grup', function (Blueprint $table) {
            $table->dropColumn('ronde');
        });
    }

    protected function backfillRondeFromCreatedAt(): void
    {
        $pairs = DB::table('grup')
            ->select('id_turnamen', 'babak')
            ->distinct()
            ->orderBy('id_turnamen')
            ->orderBy('babak')
            ->get();

        foreach ($pairs as $pair) {
            $timestamps = DB::table('grup')
                ->where('id_turnamen', $pair->id_turnamen)
                ->where('babak', $pair->babak)
                ->select('created_at')
                ->distinct()
                ->orderBy('created_at')
                ->pluck('created_at');

            foreach ($timestamps as $index => $createdAt) {
                DB::table('grup')
                    ->where('id_turnamen', $pair->id_turnamen)
                    ->where('babak', $pair->babak)
                    ->where('created_at', $createdAt)
                    ->update(['ronde' => $index + 1]);
            }
        }
    }
}
