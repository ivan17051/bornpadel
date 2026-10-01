<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPaymentStatusToTurnamenPeserta extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('turnamen_peserta')) {
            return;
        }

        if (! Schema::hasColumn('turnamen_peserta', 'payment_status')) {
            Schema::table('turnamen_peserta', function (Blueprint $table) {
                $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->after('status');
            });
        }

        DB::table('turnamen_peserta')
            ->where(function ($query) {
                $query->where('status', 'paid')
                    ->orWhereNotNull('bukti_bayar');
            })
            ->update(['payment_status' => 'paid']);

        DB::table('turnamen_peserta')
            ->whereIn('status', ['unpaid', 'paid'])
            ->update(['status' => 'pending']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE turnamen_peserta MODIFY status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down()
    {
        if (! Schema::hasTable('turnamen_peserta')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE turnamen_peserta MODIFY status ENUM('pending', 'approved', 'rejected', 'unpaid', 'paid') NOT NULL DEFAULT 'pending'");
        }

        DB::table('turnamen_peserta')
            ->where('status', 'pending')
            ->where('payment_status', 'paid')
            ->update(['status' => 'paid']);

        DB::table('turnamen_peserta')
            ->where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->update(['status' => 'unpaid']);

        if (Schema::hasColumn('turnamen_peserta', 'payment_status')) {
            Schema::table('turnamen_peserta', function (Blueprint $table) {
                $table->dropColumn('payment_status');
            });
        }
    }
}
