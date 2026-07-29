<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\DoublePairingService;
use App\Services\GroupMatchmakingService;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SingleDoublePairSemanticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_turnamen_helpers_distinguish_pair_play_modes(): void
    {
        $single = $this->createTournament('single', 'open');
        $double = $this->createTournament('double', 'open');

        $this->assertTrue($single->playsAsPairs());
        $this->assertTrue($single->randomizesPartners());
        $this->assertFalse($single->requiresPairRegistration());

        $this->assertTrue($double->playsAsPairs());
        $this->assertFalse($double->randomizesPartners());
        $this->assertTrue($double->requiresPairRegistration());
    }

    public function test_single_close_registration_randomizes_approved_solos_into_pairs(): void
    {
        $turnamen = $this->createTournament('single', 'open');
        $this->createApprovedSolos($turnamen, 6);
        $service = app(GroupMatchmakingService::class);

        $this->assertTrue($service->canCloseRegistration($turnamen));
        $result = $service->closeRegistration($turnamen);

        $this->assertSame(3, $result['pairing']['pairs_created']);
        $this->assertSame('ongoing', $turnamen->fresh()->status);
        $this->assertNotNull($turnamen->fresh()->registration_paired_at);
        $this->assertSame(3, $service->getApprovedEntries($turnamen->fresh())->count());
        $this->assertSame('pasangan', $service->unitLabel($turnamen->fresh()));
    }

    public function test_single_cannot_close_with_odd_approved_count(): void
    {
        $turnamen = $this->createTournament('single', 'open');
        $this->createApprovedSolos($turnamen, 5);
        $service = app(GroupMatchmakingService::class);

        $this->assertFalse($service->canCloseRegistration($turnamen));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jumlah pemain approved ganjil');
        $service->closeRegistration($turnamen);
    }

    public function test_double_close_requires_complete_pairs_and_never_randomizes(): void
    {
        $turnamen = $this->createTournament('double', 'open');
        $entries = $this->createApprovedSolos($turnamen, 4);
        $pairing = app(DoublePairingService::class);
        $pairing->createPair($turnamen, $entries[0], $entries[1]);
        $pairing->createPair($turnamen, $entries[2], $entries[3]);

        $service = app(GroupMatchmakingService::class);
        $this->assertTrue($service->canCloseRegistration($turnamen));

        $result = $service->closeRegistration($turnamen);

        $this->assertSame(0, $result['pairing']['pairs_created']);
        $this->assertSame(2, $turnamen->pasangan()->count());
        $this->assertSame(2, $service->getApprovedEntries($turnamen->fresh())->count());
    }

    public function test_double_cannot_close_with_approved_solos(): void
    {
        $turnamen = $this->createTournament('double', 'open');
        $entries = $this->createApprovedSolos($turnamen, 3);
        app(DoublePairingService::class)->createPair($turnamen, $entries[0], $entries[1]);

        $service = app(GroupMatchmakingService::class);
        $this->assertFalse($service->canCloseRegistration($turnamen));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tanpa pasangan');
        $service->closeRegistration($turnamen);
    }

    public function test_guest_allows_solo_double_and_rejects_pair_for_single(): void
    {
        $double = $this->createTournament('double', 'open');
        $single = $this->createTournament('single', 'open');
        $registration = app(PemainRegistrationService::class);

        $pemain = $registration->register($double, [
            'nama' => 'Solo Double',
            'gender' => 'male',
            'no_hp' => '+6281299000001',
            'rating' => 3,
        ]);

        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $double->id,
            'id_pemain1' => $pemain->id,
        ]);
        $this->assertSame(0, $double->pasangan()->count());

        try {
            $registration->registerPair(
                $single,
                [
                    'nama' => 'P1',
                    'gender' => 'male',
                    'no_hp' => '+6281299000002',
                    'rating' => 3,
                ],
                null,
                [
                    'nama' => 'P2',
                    'gender' => 'female',
                    'no_hp' => '+6281299000003',
                    'rating' => 3,
                ],
                null
            );
            $this->fail('Expected RuntimeException for single pair registration');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('double', $e->getMessage());
        }
    }

    public function test_double_pair_registration_creates_linked_rows(): void
    {
        $turnamen = $this->createTournament('double', 'open');
        $pair = app(PemainRegistrationService::class)->registerPair(
            $turnamen,
            [
                'nama' => 'Alpha',
                'gender' => 'male',
                'no_hp' => '+6281299000010',
                'rating' => 4,
            ],
            null,
            [
                'nama' => 'Bravo',
                'gender' => 'female',
                'no_hp' => '+6281299000011',
                'rating' => 4,
            ],
            null
        );

        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pair['pemain']->id,
        ]);
        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pair['partner']->id,
        ]);
        $this->assertSame(1, $turnamen->pasangan()->count());
    }

    public function test_admin_double_registration_creates_solo_then_can_be_paired(): void
    {
        $admin = User::create([
            'name' => 'Pair Admin',
            'username' => 'pair-admin-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);
        $turnamen = $this->createTournament('double', 'open');

        $this->actingAs($admin)
            ->post(route('admin.pemain.store'), [
                'id_turnamen' => $turnamen->id,
                'status' => 'approved',
                'nama' => 'Admin Solo One',
                'gender' => 'male',
                'no_hp' => '+6281299000031',
                'rating' => 3,
            ])
            ->assertRedirect(route('admin.pemain.index', ['id_turnamen' => $turnamen->id]));

        $this->assertSame(1, $turnamen->turnamenPeserta()->count());
        $this->assertSame(0, $turnamen->pasangan()->count());

        $solo = $turnamen->turnamenPeserta()->first();
        $partner = $this->createApprovedSolos($turnamen, 1)->first();
        app(DoublePairingService::class)->createPair($turnamen, $solo, $partner);

        $this->assertSame(1, $turnamen->fresh()->pasangan()->count());
    }

    public function test_guest_lookup_allows_solo_mode_for_double(): void
    {
        $turnamen = $this->createTournament('double', 'open');

        $response = $this->from(route('guest.register', ['id_turnamen' => $turnamen->id]))
            ->post(route('guest.register.lookup'), [
                'id_turnamen' => $turnamen->id,
                'registration_mode' => 'single',
                'no_hp' => '+6281299000020',
            ]);

        $response->assertRedirect(route('guest.register.form', [
            'no_hp' => '+6281299000020',
            'id_turnamen' => $turnamen->id,
            'registration_mode' => 'single',
        ]));
    }

    public function test_both_types_group_as_pair_units_after_close(): void
    {
        $single = $this->createTournament('single', 'open');
        $this->createApprovedSolos($single, 8);
        $groups = app(GroupMatchmakingService::class);
        $groups->closeRegistration($single);
        $result = $groups->generateRandomGroups($single->fresh(), 2, 2);

        $this->assertCount(2, $result['groups']);
        $this->assertSame(4, $groups->getApprovedEntries($single->fresh())->count());

        $double = $this->createTournament('double', 'open');
        $entries = $this->createApprovedSolos($double, 8);
        $pairing = app(DoublePairingService::class);
        foreach ($entries->chunk(2) as $chunk) {
            $chunk = $chunk->values();
            $pairing->createPair($double, $chunk[0], $chunk[1]);
        }
        $groups->closeRegistration($double);
        $doubleResult = $groups->generateRandomGroups($double->fresh(), 2, 2);

        $this->assertCount(2, $doubleResult['groups']);
        $this->assertSame(4, $groups->getApprovedEntries($double->fresh())->count());
    }

    protected function createTournament(string $jenis, string $status): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Pair Semantics ' . $jenis . ' ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => $jenis,
            'status' => $status,
        ]);
    }

    protected function createApprovedSolos(Turnamen $turnamen, int $count)
    {
        return collect(range(1, $count))->map(function ($index) use ($turnamen) {
            $suffix = substr(uniqid(), -6);
            $pemain = Pemain::create([
                'nama' => "Semantics Player {$index} {$suffix}",
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '+62812' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'rating' => 3 + ($index / 10),
            ]);

            return TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        })->values();
    }
}
