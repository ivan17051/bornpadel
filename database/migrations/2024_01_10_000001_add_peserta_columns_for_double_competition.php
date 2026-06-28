<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPesertaColumnsForDoubleCompetition extends Migration
{
    public function up()
    {
        Schema::table('grup_member', function (Blueprint $table) {
            $table->foreignId('id_turnamen_peserta')
                ->nullable()
                ->after('id_pemain')
                ->constrained('turnamen_peserta')
                ->nullOnDelete();
        });

        Schema::table('pertandingan', function (Blueprint $table) {
            $table->foreignId('id_peserta1')
                ->nullable()
                ->after('id_pemain2')
                ->constrained('turnamen_peserta')
                ->nullOnDelete();

            $table->foreignId('id_peserta2')
                ->nullable()
                ->after('id_peserta1')
                ->constrained('turnamen_peserta')
                ->nullOnDelete();

            $table->foreignId('id_peserta_pemenang')
                ->nullable()
                ->after('id_pemenang')
                ->constrained('turnamen_peserta')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('pertandingan', function (Blueprint $table) {
            $table->dropForeign(['id_peserta_pemenang']);
            $table->dropForeign(['id_peserta2']);
            $table->dropForeign(['id_peserta1']);
            $table->dropColumn(['id_peserta1', 'id_peserta2', 'id_peserta_pemenang']);
        });

        Schema::table('grup_member', function (Blueprint $table) {
            $table->dropForeign(['id_turnamen_peserta']);
            $table->dropColumn('id_turnamen_peserta');
        });
    }
}
