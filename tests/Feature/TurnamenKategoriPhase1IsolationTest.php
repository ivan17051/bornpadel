<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TurnamenKategoriPhase1IsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_two_categories_register_and_group_independently(): void
    {
        $turnamen = $this->createTurnamen([
            'nama' => 'Phase1 Isolation',
            'jenis' => 'single',
            'status' => 'open',
            'maks_peserta' => 32,
        ]);

        $katA = $turnamen->defaultKategori();
        $katB = TurnamenKategori::create([
            'id_turnamen' => $turnamen->id,
            'nama' => 'Open',
            'is_default' => false,
            'urutan' => 2,
            'harga' => $katA->harga,
            'maks_peserta' => 32,
            'status' => 'open',
        ]);

        $playersA = $this->createApprovedSolos($turnamen, 8, $katA->id);
        $playersB = $this->createApprovedSolos($turnamen, 8, $katB->id);

        $this->assertCount(8, $playersA);
        $this->assertCount(8, $playersB);
        $this->assertSame(8, $turnamen->competitionPeserta($katA->id)->where('status', 'approved')->count());
        $this->assertSame(8, $turnamen->competitionPeserta($katB->id)->where('status', 'approved')->count());
        $this->assertSame(16, TurnamenPeserta::query()->where('id_turnamen', $turnamen->id)->where('status', 'approved')->count());

        $service = app(GroupMatchmakingService::class);

        $service->closeRegistration($turnamen->fresh(), $katA->id);
        $service->generateRandomGroups($turnamen->fresh(), 2, 2, 'random', $katA->id);

        // 8 singles -> 4 pairs, min/max 2 pairs per group => 2 groups
        $this->assertSame(
            2,
            $turnamen->competitionGrup($katA->id)->count(),
            'Category A should have 2 groups (8 singles -> 4 pairs)'
        );
        $this->assertSame(
            0,
            $turnamen->competitionGrup($katB->id)->count(),
            'Category B groups must stay empty until its own generate'
        );

        // Multi-category: lifecycle is not mirrored onto other categories.
        $katA->refresh();
        $katB->refresh();
        $this->assertSame('ongoing', $katA->status);
        $this->assertSame('open', $katB->status);

        $service->closeRegistration($turnamen->fresh(), $katB->id);
        $service->generateRandomGroups($turnamen->fresh(), 2, 2, 'random', $katB->id);

        $this->assertSame(2, $turnamen->competitionGrup($katA->id)->count());
        $this->assertSame(2, $turnamen->competitionGrup($katB->id)->count());

        foreach ($turnamen->competitionGrup($katA->id)->get() as $grup) {
            $this->assertSame((int) $katA->id, (int) $grup->id_kategori);
        }
        foreach ($turnamen->competitionGrup($katB->id)->get() as $grup) {
            $this->assertSame((int) $katB->id, (int) $grup->id_kategori);
        }
    }

    public function test_same_player_can_register_in_two_categories(): void
    {
        $turnamen = $this->createTurnamen([
            'nama' => 'Phase1 Multi Reg',
            'jenis' => 'single',
            'status' => 'open',
        ]);

        $katA = $turnamen->defaultKategori();
        $katB = TurnamenKategori::create([
            'id_turnamen' => $turnamen->id,
            'nama' => 'Ladies',
            'is_default' => false,
            'urutan' => 2,
            'harga' => 100000,
            'maks_peserta' => 16,
            'status' => 'open',
        ]);

        $pemain = Pemain::create([
            'nama' => 'Dual Cat Player',
            'gender' => 'female',
            'no_hp' => '+62813' . random_int(10000000, 99999999),
            'rating' => 3.5,
        ]);

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katA->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katB->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        $this->assertSame(2, TurnamenPeserta::query()->where('id_pemain1', $pemain->id)->count());
        $this->assertSame(1, $turnamen->competitionPeserta($katA->id)->count());
        $this->assertSame(1, $turnamen->competitionPeserta($katB->id)->count());
    }

    public function test_is_registered_for_tournament_is_per_default_category(): void
    {
        $turnamen = $this->createTurnamen(['nama' => 'Phase1 Reg Check', 'status' => 'open']);
        $katA = $turnamen->defaultKategori();
        $katB = TurnamenKategori::create([
            'id_turnamen' => $turnamen->id,
            'nama' => 'B',
            'is_default' => false,
            'urutan' => 2,
            'harga' => 100000,
            'maks_peserta' => 16,
            'status' => 'open',
        ]);

        $pemain = Pemain::create([
            'nama' => 'Only In B',
            'gender' => 'male',
            'no_hp' => '+62814' . random_int(10000000, 99999999),
            'rating' => 3.0,
        ]);

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katB->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        $registration = app(PemainRegistrationService::class);
        // Default category path: player is only in B, not default A
        $this->assertFalse($registration->isRegisteredForTournament($pemain, $turnamen));

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katA->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        $this->assertTrue($registration->isRegisteredForTournament($pemain, $turnamen));
    }

    /**
     * @return array<int, TurnamenPeserta>
     */
    protected function createApprovedSolos(Turnamen $turnamen, int $count, int $idKategori): array
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $pemain = Pemain::create([
                'nama' => 'P1C' . $idKategori . '_' . $i . '_' . random_int(1000, 9999),
                'gender' => $i % 2 === 0 ? 'male' : 'female',
                'no_hp' => '+6281' . random_int(100000000, 999999999),
                'rating' => 3.0 + ($i % 5) * 0.2,
            ]);

            $rows[] = TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $idKategori,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }

        return $rows;
    }

    protected function createTurnamen(array $overrides = []): Turnamen
    {
        return Turnamen::create(array_merge([
            'nama' => 'Phase1 Turnamen',
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'single',
            'status' => 'draft',
        ], $overrides));
    }
}
