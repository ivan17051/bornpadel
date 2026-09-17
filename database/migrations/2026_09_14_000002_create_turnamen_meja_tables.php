<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTurnamenMejaTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('turnamen_meja')) {
            Schema::create('turnamen_meja', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_turnamen');
                $table->unsignedBigInteger('id_kategori')->nullable();
                $table->string('nama', 100);
                $table->unsignedInteger('babak')->default(1);
                $table->unsignedInteger('ronde')->default(1);
                $table->boolean('is_aktif')->default(true);
                $table->timestamps();

                $table->foreign('id_turnamen')->references('id')->on('m_turnamen')->onDelete('cascade');
                $table->index(['id_turnamen', 'id_kategori', 'is_aktif']);
                $table->index(['id_turnamen', 'babak', 'ronde']);
            });
        }

        if (! Schema::hasTable('turnamen_meja_seat')) {
            Schema::create('turnamen_meja_seat', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_meja');
                $table->unsignedBigInteger('id_grup_member');
                $table->unsignedTinyInteger('seat_order')->default(0);
                $table->timestamps();

                $table->foreign('id_meja')->references('id')->on('turnamen_meja')->onDelete('cascade');
                $table->foreign('id_grup_member')->references('id')->on('grup_member')->onDelete('cascade');
                $table->unique(['id_meja', 'id_grup_member']);
            });
        }

        if (Schema::hasTable('mahjong_poin_entry') && ! Schema::hasColumn('mahjong_poin_entry', 'id_meja')) {
            Schema::table('mahjong_poin_entry', function (Blueprint $table) {
                $table->unsignedBigInteger('id_meja')->nullable()->after('id_grup_member');
                $table->foreign('id_meja')->references('id')->on('turnamen_meja')->nullOnDelete();
                $table->index('id_meja');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('mahjong_poin_entry') && Schema::hasColumn('mahjong_poin_entry', 'id_meja')) {
            Schema::table('mahjong_poin_entry', function (Blueprint $table) {
                $table->dropForeign(['id_meja']);
                $table->dropIndex(['id_meja']);
                $table->dropColumn('id_meja');
            });
        }

        Schema::dropIfExists('turnamen_meja_seat');
        Schema::dropIfExists('turnamen_meja');
    }
}
