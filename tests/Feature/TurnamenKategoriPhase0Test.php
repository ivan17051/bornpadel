<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use App\Models\TurnamenPeserta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TurnamenKategoriPhase0Test extends TestCase
{
    use DatabaseTransactions;

    public function test_creating_turnamen_creates_default_kategori(): void
    {
        $turnamen = $this->createTurnamen([
            'nama' => 'Phase0 Default Test',
            'harga' => 150000,
            'maks_peserta' => 32,
            'status' => 'open',
        ]);

        $kategori = $turnamen->defaultKategori();

        $this->assertNotNull($kategori);
        $this->assertTrue((bool) $kategori->is_default);
        $this->assertSame('Umum', $kategori->nama);
        $this->assertEquals(150000, (float) $kategori->harga);
        $this->assertSame(32, (int) $kategori->maks_peserta);
        $this->assertSame('open', $kategori->status);
        $this->assertSame(1, $turnamen->kategori()->count());
    }

    public function test_resolve_kategori_defaults_and_rejects_foreign_id(): void
    {
        $turnamenA = $this->createTurnamen(['nama' => 'Phase0 A']);
        $turnamenB = $this->createTurnamen(['nama' => 'Phase0 B']);

        $defaultA = $turnamenA->resolveKategori();
        $this->assertSame($turnamenA->defaultKategori()->id, $defaultA->id);

        $foreignId = $turnamenB->defaultKategori()->id;

        $this->expectException(\RuntimeException::class);
        $turnamenA->resolveKategori($foreignId);
    }

    public function test_child_models_auto_assign_default_kategori(): void
    {
        $turnamen = $this->createTurnamen(['nama' => 'Phase0 Auto Assign', 'jenis' => 'single', 'status' => 'ongoing']);
        $kategoriId = $turnamen->defaultKategori()->id;

        $pemain = Pemain::create([
            'nama' => 'Phase0 Player',
            'gender' => 'male',
            'no_hp' => '+62812' . random_int(10000000, 99999999),
            'rating' => 3.0,
        ]);

        $peserta = TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        $this->assertSame($kategoriId, (int) $peserta->id_kategori);

        $grup = Grup::create([
            'id_turnamen' => $turnamen->id,
            'nama' => 'Grup A',
            'is_aktif' => true,
        ]);

        $this->assertSame($kategoriId, (int) $grup->id_kategori);

        $match = Pertandingan::create([
            'id_turnamen' => $turnamen->id,
            'id_grup' => $grup->id,
            'nama_ronde' => 'Fase Grup',
            'id_pemain1' => $pemain->id,
            'id_pemain2' => $pemain->id,
            'id_peserta1' => $peserta->id,
            'id_peserta2' => $peserta->id,
            'status' => 'scheduled',
        ]);

        $this->assertSame($kategoriId, (int) $match->id_kategori);
    }

    public function test_existing_turnamen_rows_have_kategori_after_backfill(): void
    {
        // Any pre-existing tournament row must have at least one default category
        // if the event was created after the migration (boot) or was backfilled.
        $turnamen = Turnamen::query()->first() ?: $this->createTurnamen(['nama' => 'Phase0 Seed']);

        $this->assertNotNull($turnamen->ensureDefaultKategori());
        $this->assertGreaterThanOrEqual(1, TurnamenKategori::query()->where('id_turnamen', $turnamen->id)->count());
    }

    protected function createTurnamen(array $overrides = []): Turnamen
    {
        return Turnamen::create(array_merge([
            'nama' => 'Phase0 Turnamen',
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'draft',
        ], $overrides));
    }
}
