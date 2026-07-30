<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTurnamenGrupPendaftaranTables extends Migration
{
    public function up()
    {
        Schema::create('turnamen_grup_pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turnamen')->constrained('m_turnamen')->cascadeOnDelete();
            $table->string('nama');
            $table->timestamps();

            $table->unique(['id_turnamen', 'nama'], 'turnamen_grup_pendaftaran_turnamen_nama_unique');
        });

        Schema::create('turnamen_grup_pendaftaran_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_grup_pendaftaran')
                ->constrained('turnamen_grup_pendaftaran')
                ->cascadeOnDelete();
            $table->foreignId('id_peserta')
                ->constrained('turnamen_peserta')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan')->default(1);
            $table->timestamps();

            $table->unique('id_peserta');
            $table->unique(['id_grup_pendaftaran', 'urutan'], 'grup_pendaftaran_member_urutan_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('turnamen_grup_pendaftaran_member');
        Schema::dropIfExists('turnamen_grup_pendaftaran');
    }
}
