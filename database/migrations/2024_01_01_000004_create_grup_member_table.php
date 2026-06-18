<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGrupMemberTable extends Migration
{
    public function up()
    {
        Schema::create('grup_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_grup')->constrained('grup')->cascadeOnDelete();
            $table->foreignId('id_pemain')->constrained('m_pemain')->cascadeOnDelete();
            $table->unsignedInteger('poin_didapat')->default(0);
            $table->unsignedInteger('set_menang')->default(0);
            $table->unsignedInteger('games_menang')->default(0);
            $table->timestamps();

            $table->unique(['id_grup', 'id_pemain']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grup_member');
    }
}
