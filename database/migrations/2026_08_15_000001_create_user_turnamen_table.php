<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUserTurnamenTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('user_turnamen')) {
            Schema::create('user_turnamen', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_user');
                $table->unsignedBigInteger('id_turnamen');
                $table->timestamps();

                $table->foreign('id_user')->references('id')->on('m_users')->cascadeOnDelete();
                $table->foreign('id_turnamen')->references('id')->on('m_turnamen')->cascadeOnDelete();
                $table->unique(['id_user', 'id_turnamen'], 'user_turnamen_user_turnamen_unique');
            });
        }

        if (! Schema::hasColumn('m_users', 'id_turnamen')) {
            return;
        }

        $rows = DB::table('m_users')
            ->whereNotNull('id_turnamen')
            ->get(['id', 'id_turnamen']);

        $now = now();

        foreach ($rows as $row) {
            $exists = DB::table('user_turnamen')
                ->where('id_user', $row->id)
                ->where('id_turnamen', $row->id_turnamen)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('user_turnamen')->insert([
                'id_user' => $row->id,
                'id_turnamen' => $row->id_turnamen,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('user_turnamen');
    }
}
