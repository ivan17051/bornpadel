<?php

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTurnamenPesertaTable extends Migration
{
    public function up()
    {
        Schema::create('turnamen_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turnamen')->constrained('m_turnamen')->cascadeOnDelete();
            $table->foreignId('id_pemain')->constrained('m_pemain')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->unique(['id_turnamen', 'id_pemain']);
        });

        $defaultTurnamen = Turnamen::query()->orderByDesc('doc')->first();

        if ($defaultTurnamen) {
            foreach (Pemain::all() as $pemain) {
                TurnamenPeserta::create([
                    'id_turnamen' => $defaultTurnamen->id,
                    'id_pemain' => $pemain->id,
                    'status' => $pemain->status,
                ]);
            }
        }

        Schema::table('m_pemain', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down()
    {
        Schema::table('m_pemain', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('foto');
        });

        foreach (TurnamenPeserta::with('pemain')->get() as $peserta) {
            if ($peserta->pemain) {
                $peserta->pemain->update(['status' => $peserta->status]);
            }
        }

        Schema::dropIfExists('turnamen_peserta');
    }
}
