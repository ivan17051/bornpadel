<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdNextPertandinganKalahToPertandingan extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('pertandingan') || Schema::hasColumn('pertandingan', 'id_next_pertandingan_kalah')) {
            return;
        }

        Schema::table('pertandingan', function (Blueprint $table) {
            $table->unsignedBigInteger('id_next_pertandingan_kalah')->nullable()->after('id_next_pertandingan');

            $table->foreign('id_next_pertandingan_kalah')
                ->references('id')
                ->on('pertandingan')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        if (! Schema::hasTable('pertandingan') || ! Schema::hasColumn('pertandingan', 'id_next_pertandingan_kalah')) {
            return;
        }

        Schema::table('pertandingan', function (Blueprint $table) {
            $table->dropForeign(['id_next_pertandingan_kalah']);
            $table->dropColumn('id_next_pertandingan_kalah');
        });
    }
}
