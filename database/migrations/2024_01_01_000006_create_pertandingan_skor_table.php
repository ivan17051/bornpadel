<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePertandinganSkorTable extends Migration
{
    public function up()
    {
        Schema::create('pertandingan_skor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pertandingan')->constrained('pertandingan')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_ke');
            $table->unsignedTinyInteger('skor_pemain1')->default(0);
            $table->unsignedTinyInteger('skor_pemain2')->default(0);
            $table->timestamps();

            $table->unique(['id_pertandingan', 'set_ke']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pertandingan_skor');
    }
}
