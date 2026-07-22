<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCancelledStatusToPertandinganTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('pertandingan')) {
            return;
        }

        DB::statement(
            "ALTER TABLE pertandingan MODIFY status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled'"
        );
    }

    public function down()
    {
        if (! Schema::hasTable('pertandingan')) {
            return;
        }

        DB::table('pertandingan')
            ->where('status', 'cancelled')
            ->update(['status' => 'scheduled']);

        DB::statement(
            "ALTER TABLE pertandingan MODIFY status ENUM('scheduled', 'ongoing', 'completed') NOT NULL DEFAULT 'scheduled'"
        );
    }
}
