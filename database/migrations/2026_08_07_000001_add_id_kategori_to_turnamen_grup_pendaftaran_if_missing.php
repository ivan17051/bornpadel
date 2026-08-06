<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When turnamen_grup_pendaftaran was created after the kategori backfill migration,
 * id_kategori was never added. Repair that out-of-order case.
 */
class AddIdKategoriToTurnamenGrupPendaftaranIfMissing extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('turnamen_grup_pendaftaran')
            || Schema::hasColumn('turnamen_grup_pendaftaran', 'id_kategori')) {
            return;
        }

        Schema::table('turnamen_grup_pendaftaran', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kategori')->nullable()->after('id_turnamen');
        });

        $turnamens = DB::table('m_turnamen')->orderBy('id')->get(['id']);

        foreach ($turnamens as $turnamen) {
            $kategoriId = DB::table('turnamen_kategori')
                ->where('id_turnamen', $turnamen->id)
                ->where('is_default', true)
                ->value('id');

            if (! $kategoriId) {
                $kategoriId = DB::table('turnamen_kategori')
                    ->where('id_turnamen', $turnamen->id)
                    ->orderBy('urutan')
                    ->orderBy('id')
                    ->value('id');
            }

            if (! $kategoriId) {
                continue;
            }

            DB::table('turnamen_grup_pendaftaran')
                ->where('id_turnamen', $turnamen->id)
                ->whereNull('id_kategori')
                ->update(['id_kategori' => $kategoriId]);
        }

        $nullCount = DB::table('turnamen_grup_pendaftaran')->whereNull('id_kategori')->count();

        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot enforce id_kategori on turnamen_grup_pendaftaran: {$nullCount} row(s) still null."
            );
        }

        DB::statement('ALTER TABLE `turnamen_grup_pendaftaran` MODIFY `id_kategori` BIGINT UNSIGNED NOT NULL');

        Schema::table('turnamen_grup_pendaftaran', function (Blueprint $table) {
            $table->foreign('id_kategori')
                ->references('id')
                ->on('turnamen_kategori')
                ->cascadeOnDelete();
            $table->index('id_kategori');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('turnamen_grup_pendaftaran')
            || ! Schema::hasColumn('turnamen_grup_pendaftaran', 'id_kategori')) {
            return;
        }

        Schema::table('turnamen_grup_pendaftaran', function (Blueprint $table) {
            $table->dropForeign(['id_kategori']);
            $table->dropColumn('id_kategori');
        });
    }
}
