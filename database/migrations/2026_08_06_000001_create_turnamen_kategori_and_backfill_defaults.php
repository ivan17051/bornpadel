<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTurnamenKategoriAndBackfillDefaults extends Migration
{
    protected $childTables = [
        'turnamen_peserta',
        'turnamen_pasangan',
        'turnamen_grup_pendaftaran',
        'grup',
        'pertandingan',
        'turnamen_pemenang',
    ];

    public function up()
    {
        Schema::create('turnamen_kategori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turnamen')->constrained('m_turnamen')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('urutan')->default(1);
            $table->decimal('harga', 12, 2)->default(0);
            $table->unsignedInteger('maks_peserta')->nullable();
            $table->enum('status', ['draft', 'open', 'ongoing', 'completed'])->default('draft');
            $table->timestamp('registration_paired_at')->nullable();
            $table->timestamp('group_matches_generated_at')->nullable();
            $table->boolean('mahjong_is_final')->default(false);
            $table->unsignedTinyInteger('players_per_group')->nullable();
            $table->timestamps();

            $table->index(['id_turnamen', 'is_default']);
            $table->index(['id_turnamen', 'urutan']);
        });

        foreach ($this->childTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'id_kategori')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('id_kategori')->nullable()->after('id_turnamen');
            });
        }

        $this->backfillDefaultCategoriesAndChildren();
        $this->enforceNotNullAndForeignKeys();
        $this->rewriteUniques();
    }

    public function down()
    {
        if (Schema::hasTable('turnamen_peserta') && Schema::hasColumn('turnamen_peserta', 'id_kategori')) {
            if ($this->indexExists('turnamen_peserta', 'turnamen_peserta_id_kategori_id_pemain1_unique')) {
                Schema::table('turnamen_peserta', function (Blueprint $table) {
                    $table->dropUnique('turnamen_peserta_id_kategori_id_pemain1_unique');
                });
            }
        }

        if (Schema::hasTable('turnamen_pemenang') && Schema::hasColumn('turnamen_pemenang', 'id_kategori')) {
            if ($this->indexExists('turnamen_pemenang', 'turnamen_pemenang_id_kategori_peringkat_unique')) {
                Schema::table('turnamen_pemenang', function (Blueprint $table) {
                    $table->dropUnique('turnamen_pemenang_id_kategori_peringkat_unique');
                });
            }
        }

        foreach ($this->childTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id_kategori')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['id_kategori']);
                $blueprint->dropColumn('id_kategori');
            });
        }

        Schema::dropIfExists('turnamen_kategori');

        if (Schema::hasTable('turnamen_peserta')
            && ! $this->indexExists('turnamen_peserta', 'turnamen_peserta_id_turnamen_id_pemain1_unique')) {
            Schema::table('turnamen_peserta', function (Blueprint $table) {
                $table->unique(['id_turnamen', 'id_pemain1']);
            });
        }

        if (Schema::hasTable('turnamen_pemenang')
            && ! $this->indexExists('turnamen_pemenang', 'turnamen_pemenang_id_turnamen_peringkat_unique')) {
            Schema::table('turnamen_pemenang', function (Blueprint $table) {
                $table->unique(['id_turnamen', 'peringkat']);
            });
        }
    }

    protected function backfillDefaultCategoriesAndChildren(): void
    {
        $turnamens = DB::table('m_turnamen')->orderBy('id')->get();
        $now = now();

        foreach ($turnamens as $turnamen) {
            $kategoriId = DB::table('turnamen_kategori')->insertGetId([
                'id_turnamen' => $turnamen->id,
                'nama' => 'Umum',
                'is_default' => true,
                'urutan' => 1,
                'harga' => $turnamen->harga ?? 0,
                'maks_peserta' => $turnamen->maks_peserta ?? null,
                'status' => $this->mapCategoryStatus($turnamen->status ?? 'draft'),
                'registration_paired_at' => $this->nullIfEmptyDate($turnamen->registration_paired_at ?? null),
                'group_matches_generated_at' => $this->nullIfEmptyDate($turnamen->group_matches_generated_at ?? null),
                'mahjong_is_final' => (bool) ($turnamen->mahjong_is_final ?? false),
                'players_per_group' => $turnamen->players_per_group ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($this->childTables as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id_kategori')) {
                    continue;
                }

                DB::table($table)
                    ->where('id_turnamen', $turnamen->id)
                    ->whereNull('id_kategori')
                    ->update(['id_kategori' => $kategoriId]);
            }
        }
    }

    protected function mapCategoryStatus($status): string
    {
        $allowed = ['draft', 'open', 'ongoing', 'completed'];

        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    /**
     * @param  mixed  $value
     */
    protected function nullIfEmptyDate($value)
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return $value;
    }

    protected function enforceNotNullAndForeignKeys(): void
    {
        foreach ($this->childTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id_kategori')) {
                continue;
            }

            $nullCount = DB::table($table)->whereNull('id_kategori')->count();
            if ($nullCount > 0) {
                throw new \RuntimeException(
                    "Cannot enforce id_kategori on {$table}: {$nullCount} row(s) still null."
                );
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `id_kategori` BIGINT UNSIGNED NOT NULL");

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('id_kategori')
                    ->references('id')
                    ->on('turnamen_kategori')
                    ->cascadeOnDelete();
            });
        }
    }

    protected function rewriteUniques(): void
    {
        if (Schema::hasTable('turnamen_peserta') && Schema::hasColumn('turnamen_peserta', 'id_kategori')) {
            // MySQL may use the composite unique as the supporting index for id_turnamen FK.
            if (! $this->indexExists('turnamen_peserta', 'turnamen_peserta_id_turnamen_index')) {
                Schema::table('turnamen_peserta', function (Blueprint $table) {
                    $table->index('id_turnamen', 'turnamen_peserta_id_turnamen_index');
                });
            }

            $this->dropUniqueIfExists('turnamen_peserta', 'turnamen_peserta_id_turnamen_id_pemain1_unique');
            $this->dropUniqueIfExists('turnamen_peserta', 'turnamen_peserta_id_turnamen_id_pemain_unique');

            try {
                Schema::table('turnamen_peserta', function (Blueprint $table) {
                    $table->dropUnique(['id_turnamen', 'id_pemain1']);
                });
            } catch (\Throwable $e) {
                // already dropped by named index
            }

            if (! $this->indexExists('turnamen_peserta', 'turnamen_peserta_id_kategori_id_pemain1_unique')) {
                Schema::table('turnamen_peserta', function (Blueprint $table) {
                    $table->unique(
                        ['id_kategori', 'id_pemain1'],
                        'turnamen_peserta_id_kategori_id_pemain1_unique'
                    );
                });
            }
        }

        if (Schema::hasTable('turnamen_pemenang') && Schema::hasColumn('turnamen_pemenang', 'id_kategori')) {
            if (! $this->indexExists('turnamen_pemenang', 'turnamen_pemenang_id_turnamen_index')) {
                Schema::table('turnamen_pemenang', function (Blueprint $table) {
                    $table->index('id_turnamen', 'turnamen_pemenang_id_turnamen_index');
                });
            }

            $this->dropUniqueIfExists('turnamen_pemenang', 'turnamen_pemenang_id_turnamen_peringkat_unique');

            try {
                Schema::table('turnamen_pemenang', function (Blueprint $table) {
                    $table->dropUnique(['id_turnamen', 'peringkat']);
                });
            } catch (\Throwable $e) {
                // already dropped
            }

            if (! $this->indexExists('turnamen_pemenang', 'turnamen_pemenang_id_kategori_peringkat_unique')) {
                Schema::table('turnamen_pemenang', function (Blueprint $table) {
                    $table->unique(
                        ['id_kategori', 'peringkat'],
                        'turnamen_pemenang_id_kategori_peringkat_unique'
                    );
                });
            }
        }
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
}
