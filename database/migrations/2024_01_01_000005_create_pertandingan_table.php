<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePertandinganTable extends Migration
{
    public function up()
    {
        Schema::create('pertandingan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turnamen')->constrained('m_turnamen')->cascadeOnDelete();
            $table->foreignId('id_grup')->nullable()->constrained('grup')->nullOnDelete();
            $table->enum('nama_ronde', ['Fase Grup', 'Perempatfinal', 'Semifinal', 'Final']);
            $table->foreignId('id_pemain1')->constrained('m_pemain')->restrictOnDelete();
            $table->foreignId('id_pemain2')->constrained('m_pemain')->restrictOnDelete();
            $table->foreignId('id_pemenang')->nullable()->constrained('m_pemain')->nullOnDelete();
            $table->enum('status', ['scheduled', 'ongoing', 'completed'])->default('scheduled');
            $table->unsignedBigInteger('id_next_pertandingan')->nullable();
            $table->timestamps();

            $table->foreign('id_next_pertandingan')
                ->references('id')
                ->on('pertandingan')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pertandingan');
    }
}
