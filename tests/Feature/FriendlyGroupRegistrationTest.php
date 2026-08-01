<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenGrupPendaftaran;
use App\Models\TurnamenPeserta;
use App\Services\FriendlyMatchmakingService;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FriendlyGroupRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_can_register_full_group_for_friendly(): void
    {
        $turnamen = $this->createOpenFriendly();
        $this->assertTrue($turnamen->allowsGroupRegistration());

        $players = $this->playerPayloads(1);

        $result = app(PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Alpha Wolves',
            $players,
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );

        $this->assertSame('Alpha Wolves', $result['grup_pendaftaran']->nama);
        $this->assertCount(4, $result['players']);
        $this->assertDatabaseHas('turnamen_grup_pendaftaran', [
            'id_turnamen' => $turnamen->id,
            'nama' => 'Alpha Wolves',
        ]);
        $this->assertSame(4, TurnamenPeserta::query()->forTurnamen($turnamen->id)->count());
        $this->assertSame(4, $result['grup_pendaftaran']->members()->count());
    }

    public function test_duplicate_group_name_is_rejected_case_insensitive(): void
    {
        $turnamen = $this->createOpenFriendly();
        $service = app(PemainRegistrationService::class);

        $service->registerGroup(
            $turnamen,
            'Night Owls',
            $this->playerPayloads(10),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nama grup sudah digunakan pada turnamen ini.');

        $service->assertGroupNameAvailable($turnamen, 'night owls');
    }

    public function test_duplicate_name_rejects_against_existing_competition_grup(): void
    {
        $turnamen = $this->createOpenFriendly();
        $turnamen->update(['status' => 'ongoing']);

        Grup::create([
            'id_turnamen' => $turnamen->id,
            'nama' => 'Smash Squad',
            'babak' => 1,
            'ronde' => 1,
            'is_aktif' => true,
            'poin_didapat' => 0,
            'set_menang' => 0,
            'games_menang' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nama grup sudah digunakan pada turnamen ini.');

        app(PemainRegistrationService::class)->assertGroupNameAvailable($turnamen, 'smash squad');
    }

    public function test_generate_groups_materializes_complete_pre_groups_and_randomizes_solos(): void
    {
        $turnamen = $this->createOpenFriendly();
        $service = app(PemainRegistrationService::class);

        $groupResult = $service->registerGroup(
            $turnamen,
            'Pre Group One',
            $this->playerPayloads(20),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );

        foreach ($groupResult['players'] as $pemain) {
            TurnamenPeserta::query()
                ->forTurnamen($turnamen->id)
                ->where('id_pemain1', $pemain->id)
                ->update(['status' => 'approved']);
        }

        for ($i = 1; $i <= 4; $i++) {
            $pemain = Pemain::create([
                'nama' => "Solo Player {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62877' . str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'rating' => 3.0,
            ]);
            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }

        $turnamen->update(['status' => 'ongoing']);
        $matchmaking = app(FriendlyMatchmakingService::class);
        $result = $matchmaking->generateGroups($turnamen->fresh(), 'random');

        $this->assertSame(2, $result['group_count']);
        $grupNames = $turnamen->fresh()->grup()->pluck('nama')->all();
        $this->assertContains('Pre Group One', $grupNames);

        $preGrup = $turnamen->fresh()->grup()->where('nama', 'Pre Group One')->first();
        $this->assertSame(4, $preGrup->members()->count());

        $this->assertDatabaseHas('turnamen_grup_pendaftaran', [
            'id_turnamen' => $turnamen->id,
            'nama' => 'Pre Group One',
        ]);
    }

    public function test_solo_registration_still_works_for_friendly(): void
    {
        $turnamen = $this->createOpenFriendly();

        $pemain = app(PemainRegistrationService::class)->register(
            $turnamen,
            [
                'nama' => 'Solo Friendly',
                'gender' => 'male',
                'no_hp' => '+6281999000111',
                'rating' => 2.5,
            ],
            null,
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );

        $this->assertDatabaseHas('turnamen_peserta', [
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
        ]);
        $this->assertSame(0, TurnamenGrupPendaftaran::query()->forTurnamen($turnamen->id)->count());
    }

    public function test_reset_groups_does_not_wipe_pre_groups(): void
    {
        $turnamen = $this->createOpenFriendly();
        $service = app(PemainRegistrationService::class);

        $groupResult = $service->registerGroup(
            $turnamen,
            'Keep Me',
            $this->playerPayloads(30),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );

        foreach ($groupResult['players'] as $pemain) {
            TurnamenPeserta::query()
                ->forTurnamen($turnamen->id)
                ->where('id_pemain1', $pemain->id)
                ->update(['status' => 'approved']);
        }

        for ($i = 1; $i <= 4; $i++) {
            $pemain = Pemain::create([
                'nama' => "Reset Solo {$i}",
                'gender' => 'male',
                'no_hp' => '+62866' . str_pad((string) (2000000 + $i), 7, '0', STR_PAD_LEFT),
                'rating' => 2.0,
            ]);
            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }

        $turnamen->update(['status' => 'ongoing']);
        $matchmaking = app(FriendlyMatchmakingService::class);
        $matchmaking->generateGroups($turnamen->fresh(), 'random');
        $matchmaking->resetGroupsAndMatches($turnamen->fresh());

        $this->assertSame(0, $turnamen->fresh()->grup()->count());
        $this->assertDatabaseHas('turnamen_grup_pendaftaran', [
            'id_turnamen' => $turnamen->id,
            'nama' => 'Keep Me',
        ]);
        $this->assertSame(4, TurnamenGrupPendaftaran::query()
            ->forTurnamen($turnamen->id)
            ->where('nama', 'Keep Me')
            ->first()
            ->members()
            ->count());
    }

    protected function createOpenFriendly(int $playersPerGroup = 4): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Friendly Group Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'friendly',
            'players_per_group' => $playersPerGroup,
            'status' => 'open',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function playerPayloads(int $seed, int $count = 4): array
    {
        $players = [];

        for ($i = 1; $i <= $count; $i++) {
            $players[] = [
                'nama' => "Group Player {$seed}-{$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62812' . str_pad((string) ($seed * 100 + $i), 7, '0', STR_PAD_LEFT),
                'rating' => 2.0 + $i * 0.1,
            ];
        }

        return $players;
    }

    public function test_custom_group_size_registration_and_edit_lock(): void
    {
        $turnamen = $this->createOpenFriendly(3);
        $this->assertSame(3, $turnamen->friendlyPlayersPerGroup());
        $this->assertTrue($turnamen->canEditFriendlyPlayersPerGroup());

        $result = app(PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Trio Squad',
            $this->playerPayloads(50, 3),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );

        $this->assertCount(3, $result['players']);
        $this->assertSame(3, $result['grup_pendaftaran']->members()->count());
        $this->assertFalse($turnamen->fresh()->canEditFriendlyPlayersPerGroup());
    }

    public function test_wrong_group_size_is_rejected(): void
    {
        $turnamen = $this->createOpenFriendly(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pendaftaran grup harus berisi tepat 3 pemain.');

        app(PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Wrong Size',
            $this->playerPayloads(60, 4),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );
    }
}
