<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTurnamenPemenangTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('turnamen_pemenang')) {
            return;
        }

        Schema::create('turnamen_pemenang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turnamen')->constrained('m_turnamen')->cascadeOnDelete();
            $table->unsignedTinyInteger('peringkat');
            $table->foreignId('id_pemain')->constrained('m_pemain')->cascadeOnDelete();
            $table->foreignId('id_turnamen_peserta')->nullable()->constrained('turnamen_peserta')->nullOnDelete();
            $table->unsignedInteger('total_poin')->default(0);
            $table->timestamps();

            $table->unique(['id_turnamen', 'peringkat']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('turnamen_pemenang');
    }
}
