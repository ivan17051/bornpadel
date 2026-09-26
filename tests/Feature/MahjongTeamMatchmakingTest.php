<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\MahjongPoinEntry;
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

    public function test_matchmaking_workspace_renders_mahjong_team_meja_as_round_table(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $meja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats.grupMember')
            ->first();
        $this->assertNotNull($meja);

        $emptyHtml = $this->actingAs($admin)
            ->get(route('admin.matchmaking.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mahjong-group-score-table', $emptyHtml);
        $this->assertStringContainsString('>Ronde<', $emptyHtml);
        $this->assertStringContainsString('>Subtotal<', $emptyHtml);
        $this->assertStringContainsString('Belum ada ronde.', $emptyHtml);
        $this->assertStringContainsString('btn-mahjong-input-poin', $emptyHtml);
        $this->assertStringContainsString('mahjongGroupPointsModal', $emptyHtml);
        $this->assertStringNotContainsString('btn-mahjong-team-meja-points', $emptyHtml);

        $members = $meja->seats->map->grupMember->filter()->values();
        $firstScores = $members->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [8, -2, -3, -3][$index]];
        })->all();
        $secondScores = $members->map(function (GrupMember $member, int $index) {
            return ['id' => $member->id, 'poin' => [12, -4, -4, -4][$index]];
        })->all();
        $winnerId = (int) $members->first()->id;

        $service->addMejaPointEntries($meja, $firstScores, $winnerId);
        $service->addMejaPointEntries($meja->fresh('seats.grupMember'), $secondScores, $winnerId);

        $html = $this->actingAs($admin)
            ->get(route('admin.matchmaking.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mahjong-round-row', $html);
        $this->assertStringContainsString('data-round="1"', $html);
        $this->assertStringContainsString('data-round="2"', $html);
        $this->assertStringContainsString('btn-mahjong-edit-ronde', $html);
        $this->assertStringContainsString('Bonus/Penalti', $html);
        $this->assertStringContainsString('btn-mahjong-edit-adjustment', $html);
        $this->assertStringContainsString('20 (2)', $html);

        foreach ($members as $member) {
            $this->assertStringContainsString($member->display_name, $html);
        }
    }

    public function test_matchmaking_history_lists_meja_points_expandable_per_ronde(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $meja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats.grupMember')
            ->first();
        $members = $meja->seats->map->grupMember->filter()->values();
        $service->addMejaPointEntries($meja, $members->map(fn (GrupMember $member, int $index) => [
            'id' => $member->id,
            'poin' => [10, 5, 0, -5][$index],
        ])->all(), (int) $members[0]->id);

        $service->reshuffleMeja($turnamen);

        $html = $this->actingAs($admin)
            ->get(route('admin.matchmaking.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Riwayat Meja', $html);
        $this->assertStringContainsString('mahjong-team-history-ronde-accordion', $html);
        $this->assertStringContainsString('mahjong-team-history-b1-r1', $html);
        $this->assertStringContainsString('Ronde 1', $html);
        $this->assertStringContainsString('+10', $html);
        $this->assertStringContainsString('-5', $html);
        $this->assertStringContainsString($meja->nama, $html);
        $this->assertStringContainsString('data-score-scope="table"', $html);
        $this->assertStringContainsString('Klik nomor ronde untuk mengubah skor.', $html);

        foreach ($members as $member) {
            $this->assertStringContainsString($member->display_name, $html);
        }

        $guestHtml = $this->get(route('guest.standings', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="public-mahjong-team-history-card"', $guestHtml);
        $this->assertStringContainsString('Riwayat Meja', $guestHtml);
        $this->assertStringContainsString('mahjong-team-history-ronde-accordion', $guestHtml);
        $this->assertStringContainsString($meja->nama, $guestHtml);
        $this->assertStringContainsString('+10', $guestHtml);
        $this->assertStringNotContainsString('data-score-scope="table"', $guestHtml);
        $this->assertLessThan(
            strpos($guestHtml, 'id="public-mahjong-team-history-card"'),
            strpos($guestHtml, 'id="live-leaderboard"')
        );

        foreach ($members as $member) {
            $this->assertStringContainsString($member->display_name, $guestHtml);
        }
    }

    public function test_can_update_mahjong_team_meja_round_and_keep_previous_winner(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $meja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats.grupMember')
            ->first();
        $members = $meja->seats->map->grupMember->filter()->values();
        $winnerA = (int) $members[0]->id;
        $winnerB = (int) $members[1]->id;

        $service->addMejaPointEntries($meja, $members->map(fn (GrupMember $member, int $index) => [
            'id' => $member->id,
            'poin' => [8, -2, -3, -3][$index],
        ])->all(), $winnerA);

        $service->addMejaPointEntries($meja->fresh('seats.grupMember'), $members->map(fn (GrupMember $member, int $index) => [
            'id' => $member->id,
            'poin' => [6, 2, -4, -4][$index],
        ])->all(), $winnerB);

        $roundOne = $members->mapWithKeys(function (GrupMember $member) use ($meja) {
            $entry = MahjongPoinEntry::query()
                ->where('id_grup_member', $member->id)
                ->where('id_meja', $meja->id)
                ->orderBy('id')
                ->first();

            return [$member->id => $entry];
        });
        $roundTwo = $members->mapWithKeys(function (GrupMember $member) use ($meja) {
            $entry = MahjongPoinEntry::query()
                ->where('id_grup_member', $member->id)
                ->where('id_meja', $meja->id)
                ->orderByDesc('id')
                ->first();

            return [$member->id => $entry];
        });

        $this->assertTrue((bool) $roundOne[$winnerA]->is_winner);
        $this->assertTrue((bool) $roundTwo[$winnerB]->is_winner);

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-team-meja-point-entries.update', $meja), [
                'id_grup_member_pemenang' => $winnerB,
                'scores' => $members->map(function (GrupMember $member, int $index) use ($roundTwo) {
                    return [
                        'id' => $member->id,
                        'entry_id' => $roundTwo[$member->id]->id,
                        'poin' => [10, 4, -7, -7][$index],
                    ];
                })->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.members.0.entries.1.poin', 10);

        $this->assertTrue((bool) $roundOne[$winnerA]->fresh()->is_winner);
        $this->assertSame(10, (int) $roundTwo[$winnerA]->fresh()->poin);
        $this->assertTrue((bool) $roundTwo[$winnerB]->fresh()->is_winner);

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-team-meja-point-adjustments.update', $meja), [
                'scores' => $members->map(fn (GrupMember $member, int $index) => [
                    'id' => $member->id,
                    'poin' => [1, 0, 0, -1][$index],
                ])->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, (int) $members[0]->fresh()->poin_penyesuaian);
        $this->assertSame(-1, (int) $members[3]->fresh()->poin_penyesuaian);
    }

    public function test_meja_point_entries_treat_empty_poin_as_zero(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $meja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats.grupMember')
            ->first();
        $members = $meja->seats->map->grupMember->filter()->values();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-team-meja-point-entries.store', $meja), [
                'scores' => [
                    ['id' => $members[0]->id, 'poin' => 8],
                    ['id' => $members[1]->id, 'poin' => ''],
                    ['id' => $members[2]->id],
                    ['id' => $members[3]->id, 'poin' => null],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(8, (int) $members[0]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[1]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[2]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[3]->fresh()->poin_didapat);
    }

    public function test_guest_and_admin_standings_rank_teams_with_members_listed(): void
    {
        $admin = $this->makeAdmin();
        $turnamen = $this->prepareTournament(16);
        app(MahjongTeamMatchmakingService::class)->generateTeams($turnamen, 'random');

        $teams = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with(['members.pemain'])
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(2, $teams->count());

        $leader = $teams[0];
        $runnerUp = $teams[1];

        foreach ($leader->members as $index => $member) {
            $member->update(['poin_didapat' => 40 - $index]);
        }

        foreach ($runnerUp->members as $index => $member) {
            $member->update(['poin_didapat' => 8 - $index]);
        }

        $leader->refresh()->load('members.pemain');
        $topMember = $leader->members->sortByDesc('poin_didapat')->first();

        $guest = $this->get(route('guest.standings', ['id_turnamen' => $turnamen->id]));
        $guest->assertOk();
        $guest->assertSee('Klasemen Tim', false);
        $guest->assertSee('mahjong-team-leaderboard', false);
        $guest->assertSee($leader->nama, false);
        $guest->assertSee($topMember->display_name, false);
        $guest->assertSee('40', false);
        $guest->assertSee('Peringkat berdasarkan total poin tim', false);
        $guest->assertDontSee('class="group-leaderboard"', false);

        $adminPage = $this->actingAs($admin)
            ->get(route('admin.standings.index', ['id_turnamen' => $turnamen->id]));
        $adminPage->assertOk();
        $adminPage->assertSee('Klasemen Tim', false);
        $adminPage->assertSee($leader->nama, false);
        $adminPage->assertSee($topMember->display_name, false);

        $json = $this->getJson(route('api.guest.standings', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('type', 'mahjong_team')
            ->json();

        $this->assertSame($leader->nama, $json['data'][0]['nama']);
        $this->assertSame(1, (int) $json['data'][0]['rank']);
        $this->assertSame($runnerUp->nama, $json['data'][1]['nama']);
        $this->assertSame($topMember->display_name, $json['data'][0]['members'][0]['nama']);
        $this->assertSame(40, (int) $json['data'][0]['members'][0]['poin_didapat']);
        $this->assertNotEmpty($json['data'][0]['members']);
    }

    public function test_matchmaking_history_meja_point_entries_can_be_updated_after_reshuffle(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $meja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats.grupMember')
            ->first();
        $members = $meja->seats->map->grupMember->filter()->values();

        $service->addMejaPointEntries($meja, $members->map(fn (GrupMember $member, int $index) => [
            'id' => $member->id,
            'poin' => [8, -2, -3, -3][$index],
        ])->all(), (int) $members[0]->id);

        $entries = $members->mapWithKeys(function (GrupMember $member) use ($meja) {
            $entry = MahjongPoinEntry::query()
                ->where('id_grup_member', $member->id)
                ->where('id_meja', $meja->id)
                ->first();

            return [$member->id => $entry];
        });

        $this->assertSame(8, (int) $members[0]->fresh()->poin_didapat);

        $service->reshuffleMeja($turnamen);
        $meja->refresh();
        $this->assertFalse((bool) $meja->is_aktif);

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-team-meja-point-entries.update', $meja), [
                'id_grup_member_pemenang' => (int) $members[1]->id,
                'scores' => $members->map(function (GrupMember $member, int $index) use ($entries) {
                    return [
                        'id' => $member->id,
                        'entry_id' => $entries[$member->id]->id,
                        'poin' => [12, 0, -6, -6][$index],
                    ];
                })->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(12, (int) $entries[$members[0]->id]->fresh()->poin);
        $this->assertFalse((bool) $entries[$members[0]->id]->fresh()->is_winner);
        $this->assertTrue((bool) $entries[$members[1]->id]->fresh()->is_winner);
        $this->assertSame(12, (int) $members[0]->fresh()->poin_didapat);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-team-meja-point-entries.store', $meja), [
                'scores' => $members->map(fn (GrupMember $member, int $index) => [
                    'id' => $member->id,
                    'poin' => [1, 0, 0, -1][$index],
                ])->all(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Meja tidak aktif.');
    }

    public function test_score_approval_toggle_gates_team_reshuffle_and_end_babak(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-score-approval'), [
                'id_turnamen' => $turnamen->id,
                'enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.mahjong_require_score_approval', true);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.reshuffle-groups'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Setujui skor semua pemain dulu sebelum reshuffle atau akhiri babak (16 pemain belum disetujui).');

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.end-group-stage'), [
                'id_turnamen' => $turnamen->id,
                'jumlah_lolos' => 2,
                'preview' => true,
            ])
            ->assertStatus(422);

        $html = $this->actingAs($admin)
            ->get(route('admin.matchmaking.index', ['id_turnamen' => $turnamen->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mahjong-score-approval-toggle', $html);
        $this->assertStringContainsString('btn-mahjong-approve-score', $html);
        $this->assertStringContainsString('btn-mahjong-approve-group', $html);

        $mejaList = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->get();

        foreach ($mejaList as $meja) {
            $this->actingAs($admin)
                ->patchJson(route('admin.matchmaking.mahjong-team-meja-score-approval', $meja))
                ->assertOk()
                ->assertJsonPath('success', true);
        }

        $meja = $mejaList->first()->fresh('seats.grupMember');
        $members = $meja->seats->map->grupMember->filter()->values();

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-team-meja-point-entries.store', $meja), [
                'scores' => $members->map(fn (GrupMember $member, int $index) => [
                    'id' => $member->id,
                    'poin' => [8, -2, -3, -3][$index],
                ])->all(),
                'id_grup_member_pemenang' => (int) $members[0]->id,
            ])
            ->assertOk();

        $this->assertFalse((bool) $members[0]->fresh()->poin_disetujui);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.reshuffle-groups'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertStatus(422);

        $mejaList = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->get();

        foreach ($mejaList as $item) {
            $this->actingAs($admin)
                ->patchJson(route('admin.matchmaking.mahjong-team-meja-score-approval', $item))
                ->assertOk();
        }

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.reshuffle-groups'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-score-approval'), [
                'id_turnamen' => $turnamen->id,
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.mahjong_require_score_approval', false);
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
