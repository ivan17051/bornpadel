<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use App\Models\TurnamenPeserta;
use App\Services\PemainRegistrationService;
use App\Services\TurnamenKategoriService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TurnamenKategoriPhase3GuestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_phone_can_register_in_two_categories(): void
    {
        $turnamen = $this->createTurnamen(['status' => 'open']);
        $default = $turnamen->defaultKategori();
        $default->update(['status' => 'open']);

        $extra = app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'Ladies Open',
            'harga' => 150000,
            'maks_peserta' => 16,
        ]);
        $extra->update(['status' => 'open']);

        $service = app(PemainRegistrationService::class);
        $payload = [
            'nama' => 'Multi Cat Player',
            'gender' => 'female',
            'no_hp' => '+62817' . random_int(10000000, 99999999),
            'rating' => 3,
        ];

        $pemainA = $service->register($turnamen, $payload, null, null, TurnamenPeserta::SUMBER_INTERNAL, true, $default->id);
        $pemainB = $service->register($turnamen, $payload, null, null, TurnamenPeserta::SUMBER_INTERNAL, true, $extra->id);

        $this->assertSame($pemainA->id, $pemainB->id);
        $this->assertTrue($service->isRegisteredForTournament($pemainA, $turnamen, $default->id));
        $this->assertTrue($service->isRegisteredForTournament($pemainA, $turnamen, $extra->id));
        $this->assertSame(2, TurnamenPeserta::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('id_pemain1', $pemainA->id)
            ->count());
    }

    public function test_cannot_register_twice_in_same_category(): void
    {
        $turnamen = $this->createTurnamen(['status' => 'open']);
        $kategori = $turnamen->defaultKategori();
        $kategori->update(['status' => 'open']);

        $service = app(PemainRegistrationService::class);
        $payload = [
            'nama' => 'Once Only',
            'gender' => 'male',
            'no_hp' => '+62818' . random_int(10000000, 99999999),
            'rating' => 3,
        ];

        $service->register($turnamen, $payload, null, null, TurnamenPeserta::SUMBER_INTERNAL, true, $kategori->id);

        $this->expectException(\RuntimeException::class);
        $service->register($turnamen, $payload, null, null, TurnamenPeserta::SUMBER_INTERNAL, true, $kategori->id);
    }

    public function test_guest_participants_filter_by_category(): void
    {
        $turnamen = $this->createTurnamen(['status' => 'open', 'nama' => 'Phase3 Peserta']);
        $katA = $turnamen->defaultKategori();
        $katB = app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'Category B',
            'harga' => 100000,
        ]);

        $this->seedPeserta($turnamen, $katA->id, 'Alice A', '+6281910000001');
        $this->seedPeserta($turnamen, $katB->id, 'Bob B', '+6281910000002');

        $responseA = $this->get(route('guest.participants', [
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katA->id,
        ]));
        $responseA->assertOk();
        $responseA->assertSee('Alice A');
        $responseA->assertDontSee('Bob B');

        $responseB = $this->get(route('guest.participants', [
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katB->id,
        ]));
        $responseB->assertOk();
        $responseB->assertSee('Bob B');
        $responseB->assertDontSee('Alice A');
    }

    public function test_standings_isolated_per_category(): void
    {
        $turnamen = $this->createTurnamen(['status' => 'ongoing', 'nama' => 'Phase3 Standings']);
        $katA = $turnamen->defaultKategori();
        $katB = app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'Standings B',
            'harga' => 100000,
        ]);
        $katA->update(['status' => 'ongoing']);
        $katB->update(['status' => 'ongoing']);

        $this->seedGroupWithMember($turnamen, $katA->id, 'Grup Alpha', 'Player Alpha');
        $this->seedGroupWithMember($turnamen, $katB->id, 'Grup Beta', 'Player Beta');

        $responseA = $this->get(route('guest.standings', [
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katA->id,
        ]));
        $responseA->assertOk();
        $responseA->assertSee('Grup Alpha');
        $responseA->assertDontSee('Grup Beta');

        $responseB = $this->get(route('guest.standings', [
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katB->id,
        ]));
        $responseB->assertOk();
        $responseB->assertSee('Grup Beta');
        $responseB->assertDontSee('Grup Alpha');
    }

    public function test_guest_api_standings_uses_kategori(): void
    {
        $turnamen = $this->createTurnamen(['status' => 'ongoing']);
        $katA = $turnamen->defaultKategori();
        $katB = app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'Api B',
            'harga' => 100000,
        ]);
        $katA->update(['status' => 'ongoing']);
        $katB->update(['status' => 'ongoing']);

        $this->seedGroupWithMember($turnamen, $katA->id, 'Api Alpha', 'Api Player A');
        $this->seedGroupWithMember($turnamen, $katB->id, 'Api Beta', 'Api Player B');

        $json = $this->getJson(route('api.guest.standings', [
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katA->id,
        ]))->assertOk()->json();

        $this->assertTrue($json['success'] ?? false);
        $names = collect($json['data'] ?? [])->pluck('nama')->all();
        $this->assertContains('Api Alpha', $names);
        $this->assertNotContains('Api Beta', $names);
    }

    public function test_external_api_requires_id_kategori_when_multi(): void
    {
        $turnamen = $this->createTurnamen(['status' => 'open']);
        $turnamen->defaultKategori()->update(['status' => 'open']);
        app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'External Extra',
            'harga' => 120000,
        ])->update(['status' => 'open']);

        $response = $this->withHeaders($this->externalHeaders())->postJson('/api/v1/external/register-player', [
            'id_turnamen' => $turnamen->id,
            'nama' => 'External Multi',
            'no_hp' => '+62819' . random_int(10000000, 99999999),
            'gender' => 'male',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('id_kategori', (string) $response->json('message'));
    }

    public function test_external_api_defaults_kategori_when_single(): void
    {
        $turnamen = $this->createTurnamen(['status' => 'open']);
        $kategori = $turnamen->defaultKategori();
        $kategori->update(['status' => 'open', 'harga' => 99000]);

        $phone = '+62820' . random_int(10000000, 99999999);
        $response = $this->withHeaders($this->externalHeaders())->postJson('/api/v1/external/register-player', [
            'id_turnamen' => $turnamen->id,
            'nama' => 'External Single',
            'no_hp' => $phone,
            'gender' => 'female',
        ]);

        $response->assertCreated();
        $this->assertSame((int) $kategori->id, (int) $response->json('data.kategori_id'));
        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $kategori->id,
            'id_pemain1' => $response->json('data.pemain_id'),
        ]);
    }

    public function test_guest_register_page_requires_category_when_multi(): void
    {
        $turnamen = $this->createTurnamen(['status' => 'open', 'nama' => 'Phase3 Register']);
        $turnamen->defaultKategori()->update(['status' => 'open']);
        app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'Pick Me',
            'harga' => 111000,
        ])->update(['status' => 'open']);

        $this->get(route('guest.register', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->assertSee('Pilih kategori kompetisi')
            ->assertSee('Pick Me');
    }

    protected function createTurnamen(array $overrides = []): Turnamen
    {
        return Turnamen::create(array_merge([
            'nama' => 'Phase3 Turnamen',
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'single',
            'status' => 'draft',
        ], $overrides));
    }

    protected function seedPeserta(Turnamen $turnamen, int $kategoriId, string $nama, string $phone): TurnamenPeserta
    {
        $pemain = Pemain::create([
            'nama' => $nama,
            'gender' => 'male',
            'no_hp' => $phone,
            'rating' => 3,
        ]);

        return TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $kategoriId,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);
    }

    protected function seedGroupWithMember(Turnamen $turnamen, int $kategoriId, string $grupNama, string $playerNama): void
    {
        $peserta = $this->seedPeserta(
            $turnamen,
            $kategoriId,
            $playerNama,
            '+628' . random_int(1000000000, 1999999999)
        );

        $grup = Grup::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $kategoriId,
            'nama' => $grupNama,
            'is_aktif' => true,
        ]);

        GrupMember::create([
            'id_grup' => $grup->id,
            'id_pemain' => $peserta->id_pemain1,
            'id_turnamen_peserta' => $peserta->id,
            'poin_didapat' => 3,
            'set_menang' => 1,
            'games_menang' => 6,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function externalHeaders(): array
    {
        config(['external_api.key' => 'phase3-test-api-key']);

        return [
            'X-API-Key' => 'phase3-test-api-key',
            'Accept' => 'application/json',
        ];
    }
}
