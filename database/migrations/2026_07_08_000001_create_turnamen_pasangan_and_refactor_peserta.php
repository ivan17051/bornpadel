<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTurnamenPasanganAndRefactorPeserta extends Migration
{
    public function up()
    {
        Schema::create('turnamen_pasangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turnamen')->constrained('m_turnamen')->cascadeOnDelete();
            $table->foreignId('id_peserta_1')->constrained('turnamen_peserta')->cascadeOnDelete();
            $table->foreignId('id_peserta_2')->constrained('turnamen_peserta')->cascadeOnDelete();
            $table->timestamp('paired_at')->nullable();
            $table->timestamps();

            $table->unique('id_peserta_1');
            $table->unique('id_peserta_2');
        });

        Schema::table('turnamen_peserta', function (Blueprint $table) {
            $table->enum('sumber', ['internal', 'external'])->default('internal')->after('status');
        });

        $this->migrateLegacyPairs();

        Schema::table('turnamen_peserta', function (Blueprint $table) {
            if ($this->foreignKeyExists('turnamen_peserta', 'turnamen_peserta_id_pemain2_foreign')) {
                $table->dropForeign(['id_pemain2']);
            }

            if (Schema::hasColumn('turnamen_peserta', 'id_pemain2')) {
                $table->dropColumn('id_pemain2');
            }

            if (Schema::hasColumn('turnamen_peserta', 'paired_at')) {
                $table->dropColumn('paired_at');
            }
        });
    }

    public function down()
    {
        Schema::table('turnamen_peserta', function (Blueprint $table) {
            $table->foreignId('id_pemain2')->nullable()->after('id_pemain1')->constrained('m_pemain')->cascadeOnDelete();
            $table->timestamp('paired_at')->nullable()->after('bukti_bayar');
        });

        foreach (DB::table('turnamen_pasangan')->orderBy('id')->get() as $pair) {
            DB::table('turnamen_peserta')
                ->where('id', $pair->id_peserta_1)
                ->update([
                    'id_pemain2' => DB::table('turnamen_peserta')->where('id', $pair->id_peserta_2)->value('id_pemain1'),
                    'paired_at' => $pair->paired_at,
                ]);

            DB::table('turnamen_peserta')->where('id', $pair->id_peserta_2)->delete();
        }

        Schema::table('turnamen_peserta', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });

        Schema::dropIfExists('turnamen_pasangan');
    }

    protected function migrateLegacyPairs(): void
    {
        if (! Schema::hasColumn('turnamen_peserta', 'id_pemain2')) {
            return;
        }

        $pairedRows = DB::table('turnamen_peserta')
            ->whereNotNull('id_pemain2')
            ->orderBy('id')
            ->get();

        foreach ($pairedRows as $row) {
            $now = now();

            $peserta2Id = DB::table('turnamen_peserta')->insertGetId([
                'id_turnamen' => $row->id_turnamen,
                'id_pemain1' => $row->id_pemain2,
                'status' => $row->status,
                'sumber' => 'internal',
                'bukti_bayar' => $row->bukti_bayar,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);

            DB::table('turnamen_pasangan')->insert([
                'id_turnamen' => $row->id_turnamen,
                'id_peserta_1' => $row->id,
                'id_peserta_2' => $peserta2Id,
                'paired_at' => $row->paired_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$database, $table, $foreignKey, 'FOREIGN KEY']
        );

        return $result !== [];
    }
}
