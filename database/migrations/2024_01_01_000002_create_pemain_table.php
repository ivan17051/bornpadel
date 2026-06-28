<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePemainTable extends Migration
{
    public function up()
    {
        Schema::create('m_pemain', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->date('tgl_lahir')->nullable();
            $table->unsignedTinyInteger('usia')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->string('no_hp', 20);
            $table->decimal('rating', 5, 2)->default(0);
            $table->string('foto')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('m_pemain');
    }
}
