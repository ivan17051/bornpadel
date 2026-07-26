<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BulkRegisterPemainTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_view_available_pemain_page(): void
    {
        $admin = $this->makeUser('admin');
        $turnamen = $this->createTournament('open', 'single');
        $available = Pemain::create([
            'nama' => 'Available Player',
            'gender' => 'male',
            'no_hp' => '+6281299000001',
            'rating' => 3.0,
        ]);
        $registered = Pemain::create([
            'nama' => 'Already Registered',
            'gender' => 'female',
            'no_hp' => '+6281299000002',
            'rating' => 3.5,
        ]);
        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $registered->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pemain.available', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->assertSee('Available Player')
            ->assertDontSee('Already Registered');
    }

    public function test_admin_can_bulk_register_existing_pemain(): void
    {
        $admin = $this->makeUser('admin');
        $turnamen = $this->createTournament('open', 'single');
        $p1 = Pemain::create([
            'nama' => 'Bulk One',
            'gender' => 'male',
            'no_hp' => '+6281299000011',
            'rating' => 2.5,
        ]);
        $p2 = Pemain::create([
            'nama' => 'Bulk Two',
            'gender' => 'female',
            'no_hp' => '+6281299000012',
            'rating' => 2.8,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.pemain.bulk-register'), [
                'id_turnamen' => $turnamen->id,
                'pemain_ids' => [$p1->id, $p2->id],
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.registered_count', 2);

        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $p1->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $p2->id,
            'status' => 'approved',
        ]);
    }

    public function test_bulk_register_skips_already_registered_and_works_for_double(): void
    {
        $admin = $this->makeUser('admin');
        $turnamen = $this->createTournament('open', 'double');
        $existing = Pemain::create([
            'nama' => 'Already In',
            'gender' => 'male',
            'no_hp' => '+6281299000021',
            'rating' => 3.0,
        ]);
        $fresh = Pemain::create([
            'nama' => 'Fresh Double',
            'gender' => 'female',
            'no_hp' => '+6281299000022',
            'rating' => 3.1,
        ]);
        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $existing->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.pemain.bulk-register'), [
                'id_turnamen' => $turnamen->id,
                'pemain_ids' => [$existing->id, $fresh->id],
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.registered_count', 1)
            ->assertJsonPath('data.skipped_count', 1);

        $this->assertEquals(1, TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->where('id_pemain1', $fresh->id)
            ->count());
        $this->assertEquals(1, TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->where('id_pemain1', $existing->id)
            ->count());
    }

    public function test_admin_can_create_and_register_new_pemain(): void
    {
        $admin = $this->makeUser('admin');
        $turnamen = $this->createTournament('open', 'single');

        $this->actingAs($admin)
            ->postJson(route('admin.pemain.store-new'), [
                'id_turnamen' => $turnamen->id,
                'nama' => 'Brand New Player',
                'gender' => 'male',
                'no_hp' => '+6281299000031',
                'rating' => 4.0,
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $pemain = Pemain::where('no_hp', '+6281299000031')->first();
        $this->assertNotNull($pemain);
        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
        ]);
    }

    public function test_store_new_rejects_existing_phone(): void
    {
        $admin = $this->makeUser('admin');
        $turnamen = $this->createTournament('open', 'single');
        Pemain::create([
            'nama' => 'Existing Phone',
            'gender' => 'male',
            'no_hp' => '+6281299000041',
            'rating' => 3.0,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.pemain.store-new'), [
                'id_turnamen' => $turnamen->id,
                'nama' => 'Should Fail',
                'gender' => 'female',
                'no_hp' => '+6281299000041',
                'status' => 'approved',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Nomor HP sudah ada di database. Pilih pemain dari tabel untuk mendaftarkan profil existing.']);
    }

    public function test_panitia_can_bulk_register_on_assigned_turnamen_only(): void
    {
        $turnamen = $this->createTournament('open', 'single');
        $other = $this->createTournament('open', 'single');
        $panitia = $this->makeUser('panitia', $turnamen->id);
        $pemain = Pemain::create([
            'nama' => 'Panitia Bulk',
            'gender' => 'male',
            'no_hp' => '+6281299000051',
            'rating' => 3.0,
        ]);

        $this->actingAs($panitia)
            ->postJson(route('admin.pemain.bulk-register'), [
                'id_turnamen' => $other->id,
                'pemain_ids' => [$pemain->id],
                'status' => 'approved',
            ])
            ->assertForbidden();

        $this->actingAs($panitia)
            ->postJson(route('admin.pemain.bulk-register'), [
                'id_turnamen' => $turnamen->id,
                'pemain_ids' => [$pemain->id],
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.registered_count', 1);
    }

    public function test_bulk_register_respects_capacity(): void
    {
        $admin = $this->makeUser('admin');
        $turnamen = $this->createTournament('open', 'single', 1);
        $approved = Pemain::create([
            'nama' => 'Seat Taken',
            'gender' => 'male',
            'no_hp' => '+6281299000061',
            'rating' => 3.0,
        ]);
        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $approved->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);
        $candidate = Pemain::create([
            'nama' => 'No Seat',
            'gender' => 'female',
            'no_hp' => '+6281299000062',
            'rating' => 3.0,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.pemain.bulk-register'), [
                'id_turnamen' => $turnamen->id,
                'pemain_ids' => [$candidate->id],
                'status' => 'approved',
            ])
            ->assertStatus(422);
    }

    protected function makeUser(string $role, ?int $turnamenId = null): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Bulk Test',
            'username' => $role . '-bulk-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('12345678'),
            'role' => $role,
            'id_turnamen' => $turnamenId,
        ]);
    }

    protected function createTournament(string $status, string $jenis, ?int $maks = 32): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Bulk Register Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => $maks,
            'jenis' => $jenis,
            'status' => $status,
        ]);
    }
}
