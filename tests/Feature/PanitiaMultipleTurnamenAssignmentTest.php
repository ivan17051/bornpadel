<?php

namespace Tests\Feature;

use App\Models\Turnamen;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanitiaMultipleTurnamenAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creating_panitia_with_id_turnamen_also_fills_pivot(): void
    {
        $turnamen = $this->createTurnamen('Assigned One');

        $panitia = User::create([
            'name' => 'Legacy Panitia',
            'username' => 'legacy-panitia-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('password'),
            'role' => 'panitia',
            'id_turnamen' => $turnamen->id,
        ]);

        $this->assertEquals([$turnamen->id], $panitia->assignedTurnamenIds());
        $this->assertTrue($panitia->assignedTurnamen()->where('m_turnamen.id', $turnamen->id)->exists());
    }

    public function test_admin_can_assign_multiple_tournaments_to_panitia(): void
    {
        $admin = $this->createAdmin();
        $first = $this->createTurnamen('Multi A');
        $second = $this->createTurnamen('Multi B');
        $other = $this->createTurnamen('Multi C');

        $this->actingAs($admin)
            ->post(route('admin.pengguna.store'), [
                'name' => 'Panitia Multi',
                'username' => 'panitia-multi-' . uniqid(),
                'email' => uniqid() . '@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'panitia',
                'id_turnamen' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('admin.pengguna.index'));

        $panitia = User::where('name', 'Panitia Multi')->first();
        $this->assertNotNull($panitia);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $panitia->assignedTurnamenIds()
        );
        $this->assertContains((int) $panitia->id_turnamen, [$first->id, $second->id]);

        $this->actingAs($panitia)
            ->get(route('admin.turnamen-operasi.index', ['id_turnamen' => $first->id]))
            ->assertOk();

        $this->actingAs($panitia)
            ->get(route('admin.turnamen-operasi.index', ['id_turnamen' => $second->id]))
            ->assertOk();

        $this->actingAs($panitia)
            ->get(route('admin.turnamen-operasi.index', ['id_turnamen' => $other->id]))
            ->assertForbidden();
    }

    public function test_updating_panitia_replaces_assigned_tournaments(): void
    {
        $admin = $this->createAdmin();
        $first = $this->createTurnamen('Replace A');
        $second = $this->createTurnamen('Replace B');
        $third = $this->createTurnamen('Replace C');

        $panitia = User::create([
            'name' => 'Panitia Replace',
            'username' => 'panitia-replace-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('password'),
            'role' => 'panitia',
            'id_turnamen' => $first->id,
        ]);
        $panitia->syncAssignedTurnamen([$first->id, $second->id]);

        $this->actingAs($admin)
            ->put(route('admin.pengguna.update', $panitia), [
                'name' => $panitia->name,
                'username' => $panitia->username,
                'email' => $panitia->email,
                'role' => 'panitia',
                'id_turnamen' => [$second->id, $third->id],
            ])
            ->assertRedirect(route('admin.pengguna.index'));

        $panitia->refresh();
        $this->assertEqualsCanonicalizing(
            [$second->id, $third->id],
            $panitia->assignedTurnamenIds()
        );
        $this->assertFalse(in_array($first->id, $panitia->assignedTurnamenIds(), true));
    }

    protected function createAdmin(): User
    {
        return User::create([
            'name' => 'Multi Assign Admin',
            'username' => 'multi-admin-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    protected function createTurnamen(string $nama): Turnamen
    {
        return Turnamen::create([
            'nama' => $nama . ' ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'single',
            'status' => 'open',
        ]);
    }
}
