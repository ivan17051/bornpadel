<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddBuktiBayarAndExpandStatusOnTurnamenPeserta extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('turnamen_peserta')) {
            return;
        }

        if (! Schema::hasColumn('turnamen_peserta', 'bukti_bayar')) {
            DB::statement('ALTER TABLE turnamen_peserta ADD bukti_bayar VARCHAR(255) NULL AFTER status');
        }

        DB::statement(
            "ALTER TABLE turnamen_peserta MODIFY status ENUM('pending', 'approved', 'rejected', 'unpaid', 'paid') NOT NULL DEFAULT 'pending'"
        );
    }

    public function down()
    {
        if (! Schema::hasTable('turnamen_peserta')) {
            return;
        }

        DB::statement(
            "UPDATE turnamen_peserta SET status = 'pending' WHERE status IN ('unpaid', 'paid')"
        );

        DB::statement(
            "ALTER TABLE turnamen_peserta MODIFY status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'"
        );

        if (Schema::hasColumn('turnamen_peserta', 'bukti_bayar')) {
            DB::statement('ALTER TABLE turnamen_peserta DROP COLUMN bukti_bayar');
        }
    }
}
