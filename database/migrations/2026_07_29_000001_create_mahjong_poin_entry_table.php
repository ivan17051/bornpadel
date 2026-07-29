<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMahjongPoinEntryTable extends Migration
{
    public function up()
    {
        Schema::create('mahjong_poin_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_grup_member')->constrained('grup_member')->cascadeOnDelete();
            $table->integer('poin');
            $table->timestamps();

            $table->index('id_grup_member');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mahjong_poin_entry');
    }
}
