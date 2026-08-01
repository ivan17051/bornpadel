<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->unsignedTinyInteger('players_per_group')->nullable()->after('jenis');
        });

        DB::table('m_turnamen')
            ->where('jenis', 'friendly')
            ->whereNull('players_per_group')
            ->update(['players_per_group' => 4]);
    }

    public function down(): void
    {
        Schema::table('m_turnamen', function (Blueprint $table) {
            $table->dropColumn('players_per_group');
        });
    }
};
