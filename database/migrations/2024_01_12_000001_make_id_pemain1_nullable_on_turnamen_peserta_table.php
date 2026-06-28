<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeIdPemain1NullableOnTurnamenPesertaTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('turnamen_peserta') || ! Schema::hasColumn('turnamen_peserta', 'id_pemain1')) {
            return;
        }

        $this->dropForeignKeyForColumn('turnamen_peserta', 'id_pemain1');

        DB::statement('ALTER TABLE turnamen_peserta MODIFY id_pemain1 BIGINT UNSIGNED NULL');

        DB::statement(
            'ALTER TABLE turnamen_peserta
             ADD CONSTRAINT turnamen_peserta_id_pemain1_foreign
             FOREIGN KEY (id_pemain1) REFERENCES m_pemain (id) ON DELETE CASCADE'
        );
    }

    public function down()
    {
        if (! Schema::hasTable('turnamen_peserta') || ! Schema::hasColumn('turnamen_peserta', 'id_pemain1')) {
            return;
        }

        $this->dropForeignKeyForColumn('turnamen_peserta', 'id_pemain1');

        DB::statement('ALTER TABLE turnamen_peserta MODIFY id_pemain1 BIGINT UNSIGNED NOT NULL');

        DB::statement(
            'ALTER TABLE turnamen_peserta
             ADD CONSTRAINT turnamen_peserta_id_pemain1_foreign
             FOREIGN KEY (id_pemain1) REFERENCES m_pemain (id) ON DELETE CASCADE'
        );
    }

    protected function dropForeignKeyForColumn(string $table, string $column): void
    {
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint->CONSTRAINT_NAME}");
        }
    }
}
