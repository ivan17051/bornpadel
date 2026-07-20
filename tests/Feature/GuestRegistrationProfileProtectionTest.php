<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GuestRegistrationProfileProtectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_registration_attaches_existing_profile_without_overwriting(): void
    {
        $turnamen = $this->createOpenTournament();
        $pemain = Pemain::create([
            'nama' => 'Original Player',
            'gender' => 'male',
            'no_hp' => '+6281211110001',
            'rating' => 4.5,
        ]);

        $registered = app(PemainRegistrationService::class)->register(
            $turnamen,
            [
                'nama' => 'Hijacked Name',
                'gender' => 'female',
                'no_hp' => '+6281211110001',
                'rating' => 1.0,
            ],
            null,
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );

        $this->assertSame($pemain->id, $registered->id);
        $this->assertSame('Original Player', $pemain->fresh()->nama);
        $this->assertSame('male', $pemain->fresh()->gender);
        $this->assertEquals(4.5, (float) $pemain->fresh()->rating);
        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
        ]);
    }

    public function test_admin_upsert_still_updates_existing_profile(): void
    {
        $pemain = Pemain::create([
            'nama' => 'Original Player',
            'gender' => 'male',
            'no_hp' => '+6281211110002',
            'rating' => 3.0,
        ]);

        $updated = app(PemainRegistrationService::class)->upsertPemain([
            'nama' => 'Updated By Admin',
            'gender' => 'female',
            'no_hp' => '+6281211110002',
            'rating' => 5.0,
        ]);

        $this->assertSame($pemain->id, $updated->id);
        $this->assertSame('Updated By Admin', $updated->nama);
        $this->assertSame('female', $updated->gender);
        $this->assertEquals(5.0, (float) $updated->rating);
    }

    public function test_guest_form_shows_confirm_only_for_existing_phone(): void
    {
        $turnamen = $this->createOpenTournament();
        Pemain::create([
            'nama' => 'Known Player',
            'gender' => 'male',
            'no_hp' => '+6281211110003',
            'rating' => 4.0,
        ]);

        $response = $this->get(route('guest.register.form', [
            'id_turnamen' => $turnamen->id,
            'no_hp' => '+6281211110003',
            'registration_mode' => 'single',
        ]));

        $response->assertOk();
        $response->assertSee('Profil sudah ada');
        $response->assertSee('Known Player');
        $response->assertSee('Ya, Ini Saya — Daftar');
        $response->assertDontSee('name="nama"', false);
    }

    public function test_guest_store_with_existing_phone_ignores_submitted_profile_fields(): void
    {
        $turnamen = $this->createOpenTournament();
        $pemain = Pemain::create([
            'nama' => 'Protected Player',
            'gender' => 'male',
            'no_hp' => '+6281211110004',
            'rating' => 4.2,
        ]);

        $response = $this->from(route('guest.register.form', [
            'id_turnamen' => $turnamen->id,
            'no_hp' => '+6281211110004',
        ]))->post(route('guest.register.store'), [
            'id_turnamen' => $turnamen->id,
            'registration_mode' => 'single',
            'no_hp' => '+6281211110004',
            'nama' => 'Should Not Stick',
            'gender' => 'female',
            'rating' => 0.5,
        ]);

        $response->assertRedirect(route('guest.register.success'));
        $this->assertSame('Protected Player', $pemain->fresh()->nama);
        $this->assertSame('male', $pemain->fresh()->gender);
        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
        ]);
    }

    protected function createOpenTournament(): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Open Registration Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'single',
            'status' => 'open',
        ]);
    }
}
