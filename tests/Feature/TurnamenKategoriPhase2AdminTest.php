<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\TurnamenKategoriService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TurnamenKategoriPhase2AdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_add_and_update_category_on_edit_page(): void
    {
        $admin = $this->createAdmin();
        $turnamen = $this->createTurnamen(['nama' => 'Phase2 CRUD']);

        $this->actingAs($admin)
            ->post(route('admin.turnamen.kategori.store', $turnamen), [
                'nama' => 'Open Beginner',
                'harga' => 200000,
                'maks_peserta' => 16,
            ])
            ->assertRedirect(route('admin.turnamen.edit', $turnamen));

        $extra = TurnamenKategori::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('nama', 'Open Beginner')
            ->first();

        $this->assertNotNull($extra);
        $this->assertFalse((bool) $extra->is_default);
        $this->assertEquals(200000, (float) $extra->harga);
        $this->assertSame(16, (int) $extra->maks_peserta);
        $this->assertSame('draft', $extra->status);

        $this->actingAs($admin)
            ->post(route('admin.turnamen.kategori.status', [$turnamen, $extra]), [
                'status' => 'open',
            ])
            ->assertRedirect(route('admin.turnamen.edit', $turnamen));

        $this->assertSame('open', $extra->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.turnamen.kategori.status', [$turnamen, $extra]), [
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.turnamen.edit', $turnamen));

        $this->assertSame('draft', $extra->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.turnamen.kategori.status', [$turnamen, $extra]), [
                'status' => 'open',
            ]);

        $this->actingAs($admin)
            ->put(route('admin.turnamen.kategori.update', [$turnamen, $extra]), [
                'nama' => 'Open Intermediate',
                'harga' => 250000,
                'maks_peserta' => 12,
                'urutan' => 2,
            ])
            ->assertRedirect(route('admin.turnamen.edit', $turnamen));

        $extra->refresh();
        $this->assertSame('Open Intermediate', $extra->nama);
        $this->assertEquals(250000, (float) $extra->harga);
    }

    public function test_cannot_delete_default_category(): void
    {
        $service = app(TurnamenKategoriService::class);
        $turnamen = $this->createTurnamen(['status' => 'open']);
        $default = $turnamen->defaultKategori();

        $this->assertFalse($service->canDelete($default));
        $this->expectException(\RuntimeException::class);
        $service->delete($default);
    }

    public function test_cannot_delete_category_with_participants(): void
    {
        $service = app(TurnamenKategoriService::class);
        $turnamen = $this->createTurnamen(['status' => 'open']);
        $extra = $service->create($turnamen, ['nama' => 'Ladies', 'harga' => 100000]);
        $service->transitionStatus($extra, 'open');

        $pemain = Pemain::create([
            'nama' => 'Blocked Delete',
            'gender' => 'female',
            'no_hp' => '+62815' . random_int(10000000, 99999999),
            'rating' => 3,
        ]);

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $extra->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        $this->assertFalse($service->canDelete($extra->fresh()));

        $this->expectException(\RuntimeException::class);
        $service->delete($extra->fresh());
    }

    public function test_operasi_page_scopes_pemain_to_selected_kategori(): void
    {
        $admin = $this->createAdmin();
        $turnamen = $this->createTurnamen(['nama' => 'Phase2 Operasi', 'status' => 'open']);
        $katA = $turnamen->defaultKategori();
        $katB = app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'Open B',
            'harga' => 100000,
        ]);
        app(TurnamenKategoriService::class)->transitionStatus($katB, 'open');

        $this->seedApproved($turnamen, $katA->id, 2);
        $this->seedApproved($turnamen, $katB->id, 3);

        $responseA = $this->actingAs($admin)->get(route('admin.turnamen-operasi.index', [
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katA->id,
            'tab' => 'pemain',
        ]));
        $responseA->assertOk();
        $responseA->assertSee('Open B'); // selector still shows all
        $this->assertSame(2, $responseA->viewData('pemain')->total());

        $responseB = $this->actingAs($admin)->get(route('admin.turnamen-operasi.index', [
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $katB->id,
            'tab' => 'pemain',
        ]));
        $responseB->assertOk();
        $this->assertSame(3, $responseB->viewData('pemain')->total());
    }

    public function test_empty_non_default_category_can_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $turnamen = $this->createTurnamen();
        $extra = app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'Temp Cat',
            'harga' => 50000,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.turnamen.kategori.destroy', [$turnamen, $extra]))
            ->assertRedirect(route('admin.turnamen.edit', $turnamen));

        $this->assertNull(TurnamenKategori::find($extra->id));
    }

    protected function createAdmin(): User
    {
        return User::create([
            'name' => 'Phase2 Admin',
            'username' => 'phase2admin' . random_int(1000, 9999),
            'email' => 'phase2admin' . random_int(1000, 9999) . '@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    protected function createTurnamen(array $overrides = []): Turnamen
    {
        return Turnamen::create(array_merge([
            'nama' => 'Phase2 Turnamen',
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'single',
            'status' => 'draft',
        ], $overrides));
    }

    protected function seedApproved(Turnamen $turnamen, int $kategoriId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $pemain = Pemain::create([
                'nama' => 'P2C' . $kategoriId . '_' . $i,
                'gender' => 'male',
                'no_hp' => '+62816' . random_int(10000000, 99999999),
                'rating' => 3,
            ]);
            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $kategoriId,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }
    }
}
