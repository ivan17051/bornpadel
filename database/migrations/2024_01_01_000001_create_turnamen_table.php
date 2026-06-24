<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTurnamenTable extends Migration
{
    public function up()
    {
        Schema::create('m_turnamen', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->decimal('harga', 12, 2)->default(0);
            $table->text('syarat')->nullable();
            $table->enum('jenis', ['single', 'double'])->default('single');
            $table->enum('status', ['draft', 'open', 'ongoing', 'completed'])->default('draft');
            $table->timestamp('doc')->useCurrent();
            $table->timestamp('dom')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down()
    {
        Schema::dropIfExists('m_turnamen');
    }
}
