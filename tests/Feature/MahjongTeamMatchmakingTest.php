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
use App\Services\TurnamenKategoriService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MahjongTeamMatchmakingTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_reshuffle_resets_subtotal_and_keeps_babak_total(): void
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

        $beforeDidapat = (int) GrupMember::whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->sum('poin_didapat');
        $firstMember = GrupMember::findOrFail($scores[0]['id']);
        $firstMember->update(['poin_penyesuaian' => 3]);
        $this->assertSame(10, (int) $firstMember->poin_didapat);
        $this->assertSame(0, (int) $firstMember->poin_akumulasi);
        $this->assertSame(13, (int) $firstMember->fresh()->total_poin);

        $service->reshuffleMeja($turnamen);

        $firstMember->refresh();
        $this->assertSame(0, (int) $firstMember->poin_didapat);
        $this->assertSame(3, (int) $firstMember->poin_penyesuaian);
        $this->assertSame(10, (int) $firstMember->poin_akumulasi);
        $this->assertSame(13, (int) $firstMember->total_poin);

        $afterDidapat = (int) GrupMember::whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->sum('poin_didapat');
        $afterAkumulasi = (int) GrupMember::whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))
            ->sum('poin_akumulasi');

        $this->assertSame(0, $afterDidapat);
        $this->assertSame($beforeDidapat, $afterAkumulasi);
        $this->assertSame(2, TurnamenMeja::where('id_turnamen', $turnamen->id)->where('babak', 1)->max('ronde'));

        $newMeja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->whereHas('seats', fn ($q) => $q->where('id_grup_member', $firstMember->id))
            ->with('seats')
            ->first();
        $this->assertNotNull($newMeja);

        $newScores = $newMeja->seats->map(function ($seat) use ($firstMember) {
            return [
                'id' => (int) $seat->id_grup_member,
                'poin' => (int) $seat->id_grup_member === (int) $firstMember->id ? 4 : -1,
            ];
        })->all();
        $service->addMejaPointEntries($newMeja, $newScores);

        $firstMember->refresh();
        $this->assertSame(4, (int) $firstMember->poin_didapat);
        $this->assertSame(10, (int) $firstMember->poin_akumulasi);
        $this->assertSame(3, (int) $firstMember->poin_penyesuaian);
        $this->assertSame(17, (int) $firstMember->total_poin);

        $standings = $service->teamStandings($turnamen);
        $this->assertSame(17, (int) collect($standings)->flatMap(fn ($row) => $row['members'])->firstWhere('id', $firstMember->id)['total_poin']);
        $this->assertSame(4, (int) collect($standings)->flatMap(fn ($row) => $row['members'])->firstWhere('id', $firstMember->id)['poin_didapat']);
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
                $member->update([
                    'poin_didapat' => (4 - $index) * 10,
                    'poin_penyesuaian' => 5,
                ]);
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
        $this->assertSame(0, (int) GrupMember::whereHas('grup', fn ($q) => $q->where('id_turnamen', $turnamen->id)->where('is_aktif', true))->sum('poin_penyesuaian'));

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

    public function test_generate_teams_only_uses_players_from_selected_kategori(): void
    {
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $katA = $turnamen->defaultKategori();
        $katB = app(TurnamenKategoriService::class)->create($turnamen, [
            'nama' => 'Kategori B',
            'harga' => 100000,
            'maks_peserta' => 16,
        ]);
        $katB->update(['status' => 'ongoing']);

        for ($i = 1; $i <= 16; $i++) {
            $pemain = Pemain::create([
                'nama' => "Other Cat Player {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62816'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT).$i,
                'rating' => 3,
            ]);

            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $katB->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }

        $result = $service->generateTeams($turnamen, 'random', $katA->id);

        $this->assertCount(4, $result['teams']);

        $pesertaIds = GrupMember::query()
            ->whereHas('grup', function ($query) use ($katA) {
                $query->where('id_kategori', $katA->id);
            })
            ->pluck('id_turnamen_peserta');

        $this->assertCount(16, $pesertaIds);
        $this->assertSame(
            16,
            TurnamenPeserta::query()->whereIn('id', $pesertaIds)->where('id_kategori', $katA->id)->count()
        );
        $this->assertSame(
            0,
            TurnamenPeserta::query()->whereIn('id', $pesertaIds)->where('id_kategori', $katB->id)->count()
        );
    }

    public function test_team_standings_include_bonus_penalti_in_ranking(): void
    {
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $teams = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('id')
            ->get();

        foreach ($teams[0]->members as $member) {
            $member->update(['poin_didapat' => 10, 'poin_penyesuaian' => 20]);
        }

        foreach ($teams[1]->members as $member) {
            $member->update(['poin_didapat' => 20, 'poin_penyesuaian' => 0]);
        }

        $standings = $service->teamStandings($turnamen);

        $this->assertSame($teams[0]->nama, $standings[0]['nama']);
        $this->assertSame(120, (int) $standings[0]['total_poin']);
        $this->assertSame(30, (int) $standings[0]['members'][0]['poin_babak']);
        $this->assertSame($teams[1]->nama, $standings[1]['nama']);
        $this->assertSame(80, (int) $standings[1]['total_poin']);
    }

    public function test_team_standings_break_ties_by_wins_then_akumulasi_not_team_id(): void
    {
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $teams = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('id')
            ->get();

        foreach ($teams[0]->members as $member) {
            $member->update(['poin_didapat' => 10, 'poin_akumulasi' => 0]);
        }
        foreach ($teams[1]->members as $index => $member) {
            $member->update(['poin_didapat' => 10, 'poin_akumulasi' => 0]);
            if ($index === 0) {
                MahjongPoinEntry::create([
                    'id_grup_member' => $member->id,
                    'poin' => 10,
                    'is_winner' => true,
                ]);
            }
        }

        $standings = $service->teamStandings($turnamen);
        $this->assertSame($teams[1]->id, (int) $standings[0]['id_tim']);
        $this->assertSame(1, (int) $standings[0]['menang']);
        $this->assertSame(0, (int) $standings[1]['menang']);

        foreach ($teams[0]->members as $member) {
            $member->update(['poin_didapat' => 2, 'poin_akumulasi' => 8]);
            $member->poinEntries()->delete();
        }
        foreach ($teams[1]->members as $member) {
            $member->update(['poin_didapat' => 10, 'poin_akumulasi' => 0]);
            $member->poinEntries()->delete();
        }

        $standings = $service->teamStandings($turnamen);
        $this->assertSame($teams[0]->id, (int) $standings[0]['id_tim']);
        $this->assertSame(40, (int) $standings[0]['total_poin']);
        $this->assertSame(32, (int) $standings[0]['poin_akumulasi']);
        $this->assertSame(40, (int) $standings[1]['total_poin']);
        $this->assertSame(0, (int) $standings[1]['poin_akumulasi']);
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
        $this->assertStringContainsString('btn-mahjong-delete-ronde', $html);
        $this->assertStringContainsString('data-delete-url', $html);
        $this->assertStringContainsString('data-meja-id', $html);
        $this->assertStringContainsString('mahjong-team-member-total', $html);
        $this->assertStringContainsString('mahjong-team-total', $html);
        $this->assertStringContainsString('mahjong-team-klasemen-table', $html);
        $this->assertStringContainsString('mahjong-team-rank', $html);
        $this->assertStringContainsString('data-team-menang', $html);
        $this->assertStringContainsString('pilih manual', $html);
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
        $this->assertStringContainsString('Klik nomor ronde untuk mengubah skor, atau ikon sampah untuk menghapus.', $html);
        $this->assertStringContainsString('btn-mahjong-delete-ronde', $html);
        $this->assertStringContainsString('>Total<', $html);
        $this->assertStringContainsString('Akumulasi', $html);
        $this->assertStringContainsString('Subtotal meja adalah seating saat ini', $html);

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
        $this->assertStringNotContainsString('btn-mahjong-delete-ronde', $guestHtml);
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

    public function test_can_delete_mahjong_team_meja_round_and_keep_remaining_points(): void
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

        $service->addMejaPointEntries($meja->fresh('seats.grupMember'), $members->map(fn (GrupMember $member, int $index) => [
            'id' => $member->id,
            'poin' => [12, -4, -4, -4][$index],
        ])->all(), (int) $members[0]->id);

        $roundOne = $members->mapWithKeys(function (GrupMember $member) use ($meja) {
            $entry = MahjongPoinEntry::query()
                ->where('id_grup_member', $member->id)
                ->where('id_meja', $meja->id)
                ->orderBy('id')
                ->first();

            return [$member->id => $entry];
        });

        $this->assertSame(20, (int) $members[0]->fresh()->poin_didapat);

        $this->actingAs($admin)
            ->deleteJson(route('admin.matchmaking.mahjong-team-meja-point-entries.destroy', $meja), [
                'entry_ids' => $roundOne->map(fn ($entry) => $entry->id)->values()->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(12, (int) $members[0]->fresh()->poin_didapat);
        $this->assertSame(-4, (int) $members[1]->fresh()->poin_didapat);
        $this->assertSame(1, $members[0]->fresh()->poinEntries()->where('id_meja', $meja->id)->count());
        $this->assertNull(MahjongPoinEntry::query()->find($roundOne[$members[0]->id]->id));
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
        $guest->assertSee('total poin, lalu menang, lalu akumulasi', false);
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

        $historyUpdate = $this->actingAs($admin)
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
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.members.0.entries.0.id_meja', $meja->id);

        $this->assertTrue(collect($historyUpdate->json('data.members.0.entries'))
            ->every(fn ($entry) => (int) $entry['id_meja'] === (int) $meja->id));

        $this->assertSame(12, (int) $entries[$members[0]->id]->fresh()->poin);
        $this->assertFalse((bool) $entries[$members[0]->id]->fresh()->is_winner);
        $this->assertTrue((bool) $entries[$members[1]->id]->fresh()->is_winner);
        $this->assertSame(0, (int) $members[0]->fresh()->poin_didapat);
        $this->assertSame(12, (int) $members[0]->fresh()->poin_akumulasi);
        $this->assertSame(12, (int) $members[0]->fresh()->total_poin);

        $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-team-meja-point-entries.store', $meja), [
                'scores' => $members->map(fn (GrupMember $member, int $index) => [
                    'id' => $member->id,
                    'poin' => [1, 0, 0, -1][$index],
                ])->all(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Meja tidak aktif.');

        $this->actingAs($admin)
            ->deleteJson(route('admin.matchmaking.mahjong-team-meja-point-entries.destroy', $meja), [
                'entry_ids' => $entries->map(fn ($entry) => $entry->id)->values()->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, (int) $members[0]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[0]->fresh()->poin_akumulasi);
        $this->assertSame(0, $members[0]->fresh()->poinEntries()->where('id_meja', $meja->id)->count());
    }

    public function test_live_meja_score_payload_excludes_history_entries_after_reshuffle(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $oldMeja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats.grupMember')
            ->first();
        $oldMembers = $oldMeja->seats->map->grupMember->filter()->values();
        $service->addMejaPointEntries($oldMeja, $oldMembers->map(fn (GrupMember $member, int $index) => [
            'id' => $member->id,
            'poin' => [8, -2, -3, -3][$index],
        ])->all(), (int) $oldMembers[0]->id);

        $oldEntryIds = $oldMembers->map(
            fn (GrupMember $member) => (int) $member->fresh()->poinEntries()->where('id_meja', $oldMeja->id)->value('id')
        )->all();

        $service->reshuffleMeja($turnamen);

        $newMeja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->whereHas('seats', function ($query) use ($oldMembers) {
                $query->where('id_grup_member', $oldMembers[0]->id);
            })
            ->with('seats.grupMember')
            ->first();
        $this->assertNotNull($newMeja);
        $this->assertNotSame((int) $oldMeja->id, (int) $newMeja->id);

        $newMembers = $newMeja->seats->map->grupMember->filter()->values();
        $store = $this->actingAs($admin)
            ->postJson(route('admin.matchmaking.mahjong-team-meja-point-entries.store', $newMeja), [
                'scores' => $newMembers->map(fn (GrupMember $member, int $index) => [
                    'id' => $member->id,
                    'poin' => [4, -1, -1, -2][$index],
                ])->all(),
            ])
            ->assertOk()
            ->assertJsonPath('data.meja_id', $newMeja->id);

        $payloadEntries = collect($store->json('data.members'))->flatMap(fn ($member) => $member['entries'] ?? []);
        $this->assertNotEmpty($payloadEntries);
        $this->assertTrue($payloadEntries->every(fn ($entry) => (int) $entry['id_meja'] === (int) $newMeja->id));
        $this->assertEmpty(array_intersect($oldEntryIds, $payloadEntries->pluck('id')->all()));
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

    public function test_previous_babak_history_edit_does_not_clear_current_approvals(): void
    {
        $admin = $this->makeAdmin();
        $service = app(MahjongTeamMatchmakingService::class);
        $turnamen = $this->prepareTournament(16);
        $service->generateTeams($turnamen, 'random');

        $oldMeja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats.grupMember')
            ->first();
        $oldMembers = $oldMeja->seats->map->grupMember->filter()->values();

        $service->addMejaPointEntries($oldMeja, $oldMembers->map(fn (GrupMember $member, int $index) => [
            'id' => $member->id,
            'poin' => [8, -2, -3, -3][$index],
        ])->all(), (int) $oldMembers[0]->id);

        $entries = $oldMembers->mapWithKeys(function (GrupMember $member) use ($oldMeja) {
            $entry = MahjongPoinEntry::query()
                ->where('id_grup_member', $member->id)
                ->where('id_meja', $oldMeja->id)
                ->first();

            return [$member->id => $entry];
        });

        $teams = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->orderBy('id')
            ->get();

        foreach ($teams as $index => $team) {
            foreach ($team->members as $member) {
                $member->update(['poin_didapat' => (4 - $index) * 10]);
            }
        }

        $service->advanceTeams($turnamen, 2);
        $oldMeja->refresh();
        $this->assertFalse((bool) $oldMeja->is_aktif);
        $this->assertSame(1, (int) $oldMeja->babak);

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-score-approval'), [
                'id_turnamen' => $turnamen->id,
                'enabled' => true,
            ])
            ->assertOk();

        $activeMeja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->get();

        foreach ($activeMeja as $meja) {
            $this->actingAs($admin)
                ->patchJson(route('admin.matchmaking.mahjong-team-meja-score-approval', $meja))
                ->assertOk();
        }

        $seated = GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
            })
            ->where('poin_disetujui', true)
            ->first();

        $this->assertNotNull($seated);
        $poinBefore = (int) $seated->poin_didapat;

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-team-meja-point-entries.update', $oldMeja), [
                'id_grup_member_pemenang' => (int) $oldMembers[1]->id,
                'scores' => $oldMembers->map(function (GrupMember $member, int $index) use ($entries) {
                    return [
                        'id' => $member->id,
                        'entry_id' => $entries[$member->id]->id,
                        'poin' => [12, 0, -6, -6][$index],
                    ];
                })->all(),
            ])
            ->assertOk();

        $seated->refresh();
        $this->assertTrue((bool) $seated->poin_disetujui);
        $this->assertSame($poinBefore, (int) $seated->poin_didapat);
        $this->assertSame(12, (int) $entries[$oldMembers[0]->id]->fresh()->poin);
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
