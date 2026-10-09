<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllowSharedRankOnTurnamenPemenang extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('turnamen_pemenang') || ! Schema::hasColumn('turnamen_pemenang', 'id_kategori')) {
            return;
        }

        $this->replaceUniqueIndex(
            'turnamen_pemenang_id_kategori_peringkat_unique',
            ['id_kategori', 'id_pemain'],
            'turnamen_pemenang_id_kategori_id_pemain_unique'
        );
    }

    public function down()
    {
        if (! Schema::hasTable('turnamen_pemenang') || ! Schema::hasColumn('turnamen_pemenang', 'id_kategori')) {
            return;
        }

        $this->replaceUniqueIndex(
            'turnamen_pemenang_id_kategori_id_pemain_unique',
            ['id_kategori', 'peringkat'],
            'turnamen_pemenang_id_kategori_peringkat_unique'
        );
    }

    /**
     * @param  list<string>  $newColumns
     */
    protected function replaceUniqueIndex(string $oldIndex, array $newColumns, string $newIndex): void
    {
        $droppedForeign = $this->dropKategoriForeignIfExists();

        $this->dropUniqueIfExists('turnamen_pemenang', $oldIndex);

        if (! $this->indexExists('turnamen_pemenang', $newIndex)) {
            Schema::table('turnamen_pemenang', function (Blueprint $table) use ($newColumns, $newIndex) {
                $table->unique($newColumns, $newIndex);
            });
        }

        if ($droppedForeign) {
            Schema::table('turnamen_pemenang', function (Blueprint $table) {
                $table->foreign('id_kategori', 'turnamen_pemenang_id_kategori_foreign')
                    ->references('id')
                    ->on('turnamen_kategori')
                    ->cascadeOnDelete();
            });
        }
    }

    protected function dropKategoriForeignIfExists(): bool
    {
        if (! $this->foreignKeyExists('turnamen_pemenang', 'turnamen_pemenang_id_kategori_foreign')) {
            return false;
        }

        Schema::table('turnamen_pemenang', function (Blueprint $table) {
            $table->dropForeign('turnamen_pemenang_id_kategori_foreign');
        });

        return true;
    }

    protected function dropUniqueIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropUnique($indexName);
        });
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(1) AS aggregate
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?',
            [$database, $table, $indexName]
        );

        return $row && (int) $row->aggregate > 0;
    }

    protected function foreignKeyExists(string $table, string $constraintName): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(1) AS aggregate
             FROM information_schema.table_constraints
             WHERE table_schema = ?
               AND table_name = ?
               AND constraint_name = ?
               AND constraint_type = ?',
            [$database, $table, $constraintName, 'FOREIGN KEY']
        );

        return $row && (int) $row->aggregate > 0;
    }
}
