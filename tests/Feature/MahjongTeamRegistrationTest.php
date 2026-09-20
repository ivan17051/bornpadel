<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\MahjongTeamMatchmakingService;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MahjongTeamRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_register_full_team_for_mahjong_team(): void
    {
        $turnamen = $this->createOpenMahjongTeam();
        $this->assertTrue($turnamen->allowsGroupRegistration());
        $this->assertSame(4, $turnamen->registrationRosterSize());
        $this->assertSame('tim', $turnamen->registrationRosterNoun());

        $result = app(PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Dragon Squad',
            $this->playerPayloads(1),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false,
            'approved'
        );

        $this->assertSame('Dragon Squad', $result['grup_pendaftaran']->nama);
        $this->assertCount(4, $result['players']);
        $this->assertTrue($result['grup_pendaftaran']->isFullyApproved($turnamen));
        $this->assertSame(4, TurnamenPeserta::query()->forTurnamen($turnamen->id)->count());
    }

    public function test_team_registration_requires_exactly_four_players(): void
    {
        $turnamen = $this->createOpenMahjongTeam();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pendaftaran tim harus berisi tepat 4 pemain.');

        app(PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Too Small',
            $this->playerPayloads(2, 3),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false
        );
    }

    public function test_guest_register_page_offers_team_mode(): void
    {
        $turnamen = $this->createOpenMahjongTeam();

        $this->get(route('guest.register', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->assertSee('Satu Tim (4 pemain)')
            ->assertSee('Nama Tim')
            ->assertSee('daftar satu tim lengkap');
    }

    public function test_guest_can_submit_mahjong_team_registration(): void
    {
        $turnamen = $this->createOpenMahjongTeam();
        $players = $this->playerPayloads(8);

        $this->from(route('guest.register.form', [
            'id_turnamen' => $turnamen->id,
            'registration_mode' => 'group',
            'nama_grup' => 'Phoenix Nine',
            'no_hp' => $players[0]['no_hp'],
            'no_hp_2' => $players[1]['no_hp'],
            'no_hp_3' => $players[2]['no_hp'],
            'no_hp_4' => $players[3]['no_hp'],
        ]))->post(route('guest.register.store'), [
            'id_turnamen' => $turnamen->id,
            'registration_mode' => 'group',
            'nama_grup' => 'Phoenix Nine',
            'no_hp' => $players[0]['no_hp'],
            'nama' => $players[0]['nama'],
            'gender' => $players[0]['gender'],
            'player_2' => [
                'no_hp' => $players[1]['no_hp'],
                'nama' => $players[1]['nama'],
                'gender' => $players[1]['gender'],
            ],
            'player_3' => [
                'no_hp' => $players[2]['no_hp'],
                'nama' => $players[2]['nama'],
                'gender' => $players[2]['gender'],
            ],
            'player_4' => [
                'no_hp' => $players[3]['no_hp'],
                'nama' => $players[3]['nama'],
                'gender' => $players[3]['gender'],
            ],
        ])->assertRedirect(route('guest.register.success'));

        $this->assertDatabaseHas('turnamen_grup_pendaftaran', [
            'id_turnamen' => $turnamen->id,
            'nama' => 'Phoenix Nine',
        ]);
        $this->assertSame(4, TurnamenPeserta::query()->forTurnamen($turnamen->id)->count());
    }

    public function test_generate_teams_keeps_registered_team_rosters(): void
    {
        $turnamen = $this->createOpenMahjongTeam();
        $service = app(PemainRegistrationService::class);

        $dragons = $service->registerGroup(
            $turnamen,
            'Dragon Squad',
            $this->playerPayloads(1),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false,
            'approved'
        );
        $tigers = $service->registerGroup(
            $turnamen,
            'Tiger Clan',
            $this->playerPayloads(2),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false,
            'approved'
        );

        for ($i = 1; $i <= 8; $i++) {
            $pemain = Pemain::create([
                'nama' => "Solo MJ {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62819'.str_pad((string) (4000000 + $i), 7, '0', STR_PAD_LEFT),
                'rating' => 2.0,
            ]);

            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }

        app(GroupMatchmakingService::class)->closeRegistration($turnamen->fresh());
        $result = app(MahjongTeamMatchmakingService::class)->generateTeams($turnamen->fresh(), 'random');

        $this->assertCount(4, $result['teams']);
        $names = collect($result['teams'])->pluck('nama')->all();
        $this->assertContains('Dragon Squad', $names);
        $this->assertContains('Tiger Clan', $names);

        $dragonTeam = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('nama', 'Dragon Squad')
            ->with('members')
            ->first();
        $tigerTeam = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('nama', 'Tiger Clan')
            ->with('members')
            ->first();

        $this->assertNotNull($dragonTeam);
        $this->assertNotNull($tigerTeam);
        $this->assertSame(
            $dragons['players']->pluck('id')->sort()->values()->all(),
            $dragonTeam->members->pluck('id_pemain')->sort()->values()->all()
        );
        $this->assertSame(
            $tigers['players']->pluck('id')->sort()->values()->all(),
            $tigerTeam->members->pluck('id_pemain')->sort()->values()->all()
        );
    }

    protected function createOpenMahjongTeam(): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Mahjong Team Reg '.uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 32,
            'jenis' => 'mahjong_team',
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
                'nama' => "Team Player {$seed}-{$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62813'.str_pad((string) ($seed * 100 + $i), 7, '0', STR_PAD_LEFT),
                'rating' => 2.0 + $i * 0.1,
            ];
        }

        return $players;
    }
}
