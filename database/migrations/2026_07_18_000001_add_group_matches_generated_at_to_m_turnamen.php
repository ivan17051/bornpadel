<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('m_turnamen', 'group_matches_generated_at')) {
            Schema::table('m_turnamen', function (Blueprint $table) {
                $table->timestamp('group_matches_generated_at')
                    ->nullable()
                    ->after('registration_paired_at');
            });
        }

        DB::table('m_turnamen')
            ->whereIn('jenis', ['single', 'double'])
            ->whereNull('group_matches_generated_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('pertandingan')
                    ->whereColumn('pertandingan.id_turnamen', 'm_turnamen.id')
                    ->whereNotNull('pertandingan.id_grup')
                    ->where('pertandingan.nama_ronde', 'Fase Grup');
            })
            ->update(['group_matches_generated_at' => DB::raw('COALESCE(dom, doc, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('m_turnamen', 'group_matches_generated_at')) {
            Schema::table('m_turnamen', function (Blueprint $table) {
                $table->dropColumn('group_matches_generated_at');
            });
        }
    }
};
