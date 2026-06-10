<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGrupTable extends Migration
{
    public function up()
    {
        Schema::create('grup', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turnamen')->constrained('turnamen')->cascadeOnDelete();
            $table->string('nama');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('grup');
    }
}
