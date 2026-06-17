<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleAndIdTurnamenToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'panitia'])->default('panitia')->after('password');
            $table->unsignedBigInteger('id_turnamen')->nullable()->after('role');
            $table->foreign('id_turnamen')->references('id')->on('turnamen')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_turnamen']);
            $table->dropColumn(['role', 'id_turnamen']);
        });
    }
}
