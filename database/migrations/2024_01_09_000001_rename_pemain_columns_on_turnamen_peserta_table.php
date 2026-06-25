<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenamePemainColumnsOnTurnamenPesertaTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('turnamen_peserta')) {
            return;
        }

        if (Schema::hasColumn('turnamen_peserta', 'id_pemain') && ! Schema::hasColumn('turnamen_peserta', 'id_pemain1')) {
            $this->dropForeignKeyForColumn('turnamen_peserta', 'id_turnamen');
            $this->dropForeignKeyForColumn('turnamen_peserta', 'id_pemain');

            if ($this->indexExists('turnamen_peserta', 'turnamen_peserta_id_turnamen_id_pemain_unique')) {
                Schema::table('turnamen_peserta', function (Blueprint $table) {
                    $table->dropUnique('turnamen_peserta_id_turnamen_id_pemain_unique');
                });
            }

            DB::statement('ALTER TABLE turnamen_peserta CHANGE id_pemain id_pemain1 BIGINT UNSIGNED NOT NULL');

            Schema::table('turnamen_peserta', function (Blueprint $table) {
                $table->foreign('id_turnamen')->references('id')->on('m_turnamen')->cascadeOnDelete();
                $table->foreign('id_pemain1')->references('id')->on('m_pemain')->cascadeOnDelete();
                $table->unique(['id_turnamen', 'id_pemain1']);
            });
        }

        if (! Schema::hasColumn('turnamen_peserta', 'id_pemain2')) {
            Schema::table('turnamen_peserta', function (Blueprint $table) {
                $table->foreignId('id_pemain2')
                    ->nullable()
                    ->after('id_pemain1')
                    ->constrained('m_pemain')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('turnamen_peserta')) {
            return;
        }

        if (Schema::hasColumn('turnamen_peserta', 'id_pemain2')) {
            $this->dropForeignKeyForColumn('turnamen_peserta', 'id_pemain2');

            Schema::table('turnamen_peserta', function (Blueprint $table) {
                $table->dropColumn('id_pemain2');
            });
        }

        if (Schema::hasColumn('turnamen_peserta', 'id_pemain1') && ! Schema::hasColumn('turnamen_peserta', 'id_pemain')) {
            $this->dropForeignKeyForColumn('turnamen_peserta', 'id_turnamen');
            $this->dropForeignKeyForColumn('turnamen_peserta', 'id_pemain1');

            if ($this->indexExists('turnamen_peserta', 'turnamen_peserta_id_turnamen_id_pemain1_unique')) {
                Schema::table('turnamen_peserta', function (Blueprint $table) {
                    $table->dropUnique(['id_turnamen', 'id_pemain1']);
                });
            }

            DB::statement('ALTER TABLE turnamen_peserta CHANGE id_pemain1 id_pemain BIGINT UNSIGNED NOT NULL');

            Schema::table('turnamen_peserta', function (Blueprint $table) {
                $table->foreign('id_turnamen')->references('id')->on('m_turnamen')->cascadeOnDelete();
                $table->foreign('id_pemain')->references('id')->on('m_pemain')->cascadeOnDelete();
                $table->unique(['id_turnamen', 'id_pemain']);
            });
        }
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

    protected function indexExists(string $table, string $index): bool
    {
        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);

        return ! empty($result);
    }
}
