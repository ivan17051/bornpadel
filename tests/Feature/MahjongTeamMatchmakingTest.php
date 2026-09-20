<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenMeja;
use App\Models\TurnamenMejaSeat;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\MahjongTeamMatchmakingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MahjongTeamMatchmakingTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_teams_and_meja_for_four_teams(): void
    {
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $result = $service->generateTeams($turnamen, 'random');

        $this->assertCount(4, $result['teams']);
        $this->assertCount(4, $result['meja']);
        $this->assertSame(4, Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->count());
        $this->assertSame(4, TurnamenMeja::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->count());
    }

    public function test_reshuffle_keeps_babak_points(): void
    {
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $meja = TurnamenMeja::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->with('seats')->first();
        $scores = $meja->seats->map(fn ($seat, $i) => [
            'id' => (int) $seat->id_grup_member,
            'poin' => [10, 5, 0, -5][$i],
        ])->all();
        $service->addMejaPointEntries($meja, $scores);

        $before = GrupMember::whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->sum('poin_didapat');

        $service->reshuffleMeja($turnamen);

        $after = GrupMember::whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->sum('poin_didapat');

        $this->assertSame((int) $before, (int) $after);
        $this->assertSame(2, TurnamenMeja::where('id_turnamen', $turnamen->id)->where('babak', 1)->max('ronde'));
    }

    public function test_advance_resets_points_and_can_crown_champion(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $teams = Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->with('members')->orderBy('id')->get();
        foreach ($teams as $index => $team) {
            foreach ($team->members as $member) {
                $member->update(['poin_didapat' => (4 - $index) * 10]);
            }
        }

        $preview = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 2,
                'preview' => true,
            ])
            ->assertOk()
            ->assertJsonPath('preview', true);

        $this->assertCount(2, $preview->json('data.qualifiers'));

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_champion', false);

        $this->assertSame(2, Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->count());
        $this->assertSame(0, (int) GrupMember::whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))->sum('poin_didapat'));

        $remaining = Grup::where('id_turnamen', $turnamen->id)->where('is_aktif', true)->with('members')->orderBy('id')->get();
        foreach ($remaining as $index => $team) {
            foreach ($team->members as $member) {
                $member->update(['poin_didapat' => (2 - $index) * 20]);
            }
        }

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_champion', true);

        $this->assertTrue($service->canComplete($turnamen->fresh()));
    }

    public function test_generate_rejects_wrong_player_count(): void
    {
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(12);

        $this->expectException(\RuntimeException::class);
        $service->generateTeams($turnamen, 'random');
    }

    public function test_close_registration_rejects_invalid_starting_roster(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->prepareTournament(12);
        $turnamen->update(['status' => 'open']);
        $turnamen->resolveKategori()->update(['status' => 'open']);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.close-registration'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Jumlah tim awal harus 4 atau 8 (16 atau 32 pemain).');

        $this->assertFalse(app(\App\Services\GroupMatchmakingService::class)->canCloseRegistration($turnamen->fresh()));
    }

    public function test_close_registration_allows_sixteen_players(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->prepareTournament(16);
        $turnamen->update(['status' => 'open']);
        $turnamen->resolveKategori()->update(['status' => 'open']);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.close-registration'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertOk();

        $this->assertSame('ongoing', $turnamen->fresh()->status);
    }

    public function test_generate_five_player_teams_sits_out_one_per_team(): void
    {
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(20, 5);
        $result = $service->generateTeams($turnamen, 'random');

        $this->assertCount(4, $result['teams']);
        $this->assertCount(4, $result['meja']);

        $teams = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->get();

        $this->assertCount(4, $teams);
        foreach ($teams as $team) {
            $this->assertCount(5, $team->members);
        }

        $seatedIds = TurnamenMejaSeat::query()
            ->whereHas('meja', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
            })
            ->pluck('id_grup_member');

        $this->assertCount(16, $seatedIds);
        foreach ($teams as $team) {
            $this->assertSame(4, $team->members->whereIn('id', $seatedIds)->count());
        }
    }

    public function test_close_registration_uses_custom_team_size(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->prepareTournament(15, 5);
        $turnamen->update(['status' => 'open']);
        $turnamen->resolveKategori()->update(['status' => 'open']);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.close-registration'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Jumlah tim awal harus 4 atau 8 (20 atau 40 pemain).');

        $turnamenOk = $this->prepareTournament(20, 5);
        $turnamenOk->update(['status' => 'open']);
        $turnamenOk->resolveKategori()->update(['status' => 'open']);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.close-registration'), [
                'id_turnamen' => $turnamenOk->id,
            ])
            ->assertOk();

        $this->assertSame('ongoing', $turnamenOk->fresh()->status);
    }

    protected function prepareTournament(int $playerCount, int $playersPerTeam = 4): Turnamen
    {
        $turnamen = Turnamen::create([
            'nama' => 'Mahjong Team Test '.uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => $playerCount,
            'jenis' => 'mahjong_team',
            'players_per_group' => $playersPerTeam,
            'status' => 'ongoing',
        ]);

        for ($i = 1; $i <= $playerCount; $i++) {
            $pemain = Pemain::create([
                'nama' => "Team Player {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62817'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT).$i,
                'rating' => 2.5 + ($i * 0.1),
            ]);

            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }

        return $turnamen;
    }

    protected function makeAdmin(): User
    {
        return User::create([
            'name' => 'Team Admin',
            'username' => 'team-admin-'.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);
    }
}
