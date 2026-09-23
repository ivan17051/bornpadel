<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenMeja;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\MahjongMatchmakingService;
use App\Services\MahjongTeamMatchmakingService;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExternalMahjongScoreApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_external_api_rejects_non_mahjong_tournament(): void
    {
        $turnamen = Turnamen::create([
            'nama' => 'Padel External ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'ongoing',
        ]);

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-scores', [
                'id_grup' => 1,
                'id_grup_member_pemenang' => 1,
                'scores' => [
                    ['id_grup_member' => 1, 'poin' => 1],
                    ['id_grup_member' => 2, 'poin' => 1],
                    ['id_grup_member' => 3, 'poin' => 1],
                    ['id_grup_member' => 4, 'poin' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Endpoint ini hanya tersedia untuk turnamen Mahjong atau Mahjong Tim.');
    }

    public function test_external_api_stores_group_scores_and_updates_entry(): void
    {
        $turnamen = $this->prepareMahjongTournament(8);
        app(MahjongMatchmakingService::class)->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $this->assertNotNull($grup);
        $this->assertCount(4, $grup->members);

        $points = [8, -2, -3, -3];
        $scores = $grup->members->values()->map(function (GrupMember $member, int $index) use ($points) {
            return [
                'id_grup_member' => $member->id,
                'poin' => $points[$index],
            ];
        })->all();

        $store = $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-scores', [
                'id_grup' => $grup->id,
                'id_grup_member_pemenang' => $grup->members->first()->id,
                'scores' => $scores,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertCount(4, $store->json('data.members'));
        $winnerId = (int) $grup->members->first()->id;
        $this->assertSame(1, (int) collect($store->json('data.members'))->firstWhere('id_grup_member', $winnerId)['menang']);

        foreach ($scores as $score) {
            $member = GrupMember::findOrFail($score['id_grup_member']);
            $this->assertSame($score['poin'], (int) $member->poin_didapat);
            $this->assertSame(1, $member->poinEntries()->count());
            $this->assertSame((int) $member->id === $winnerId, (bool) $member->poinEntries()->first()->is_winner);
        }

        $firstMember = $grup->members->first();
        $entry = $firstMember->fresh()->poinEntries()->first();
        $this->assertNotNull($entry);

        $this->withHeaders($this->externalHeaders())
            ->patchJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-scores/'.$entry->id, [
                'poin' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.poin_didapat', 12);

        $this->assertSame(12, (int) $entry->fresh()->poin);
        $this->assertSame(12, (int) $firstMember->fresh()->poin_didapat);
    }

    public function test_external_api_allows_group_scores_without_winner(): void
    {
        $turnamen = $this->prepareMahjongTournament(8);
        app(MahjongMatchmakingService::class)->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $scores = $grup->members->values()->map(function (GrupMember $member, int $index) {
            return [
                'id_grup_member' => $member->id,
                'poin' => [5, -1, -2, -2][$index],
            ];
        })->all();

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-scores', [
                'id_grup' => $grup->id,
                'scores' => $scores,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        foreach ($grup->members as $member) {
            $entry = $member->fresh()->poinEntries()->first();
            $this->assertNotNull($entry);
            $this->assertFalse((bool) $entry->is_winner);
            $this->assertSame(0, (int) $member->fresh()->menang);
        }
    }

    public function test_external_api_treats_empty_poin_as_zero(): void
    {
        $turnamen = $this->prepareMahjongTournament(8);
        app(MahjongMatchmakingService::class)->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $members = $grup->members->values();

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-scores', [
                'id_grup' => $grup->id,
                'scores' => [
                    ['id_grup_member' => $members[0]->id, 'poin' => 8],
                    ['id_grup_member' => $members[1]->id, 'poin' => ''],
                    ['id_grup_member' => $members[2]->id],
                    ['id_grup_member' => $members[3]->id, 'poin' => null],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertSame(8, (int) $members[0]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[1]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[2]->fresh()->poin_didapat);
        $this->assertSame(0, (int) $members[3]->fresh()->poin_didapat);

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-members/'.$members[1]->id.'/scores', [
                'poin' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('data.poin_didapat', 0);
    }

    public function test_external_api_rejects_writes_when_scoring_disabled(): void
    {
        $admin = User::create([
            'name' => 'Mahjong Toggle Admin',
            'username' => 'mahjong-toggle-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);

        $mahjong = app(MahjongMatchmakingService::class);
        $turnamen = $this->prepareMahjongTournament(8);
        $mahjong->generateGroups($turnamen, 'random');

        $grup = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('members')
            ->first();

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-external-scoring'), [
                'id_turnamen' => $turnamen->id,
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.mahjong_external_scoring_enabled', false);

        $this->assertFalse($mahjong->isExternalScoringEnabled($turnamen->fresh()));

        $scores = $grup->members->values()->map(function (GrupMember $member) {
            return ['id_grup_member' => $member->id, 'poin' => 1];
        })->all();

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-scores', [
                'id_grup' => $grup->id,
                'scores' => $scores,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $member = $grup->members->first();

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-members/'.$member->id.'/scores', [
                'poin' => 3,
            ])
            ->assertStatus(403);

        // Read endpoints remain available.
        $this->withHeaders($this->externalHeaders())
            ->getJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-groups')
            ->assertOk()
            ->assertJsonPath('data.turnamen.mahjong_external_scoring_enabled', false);

        $this->actingAs($admin)
            ->patchJson(route('admin.matchmaking.mahjong-external-scoring'), [
                'id_turnamen' => $turnamen->id,
                'enabled' => true,
            ])
            ->assertOk();

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-members/'.$member->id.'/scores', [
                'poin' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.poin_didapat', 3);
    }

    public function test_external_api_stores_single_member_score(): void
    {
        $turnamen = $this->prepareMahjongTournament(8);
        app(MahjongMatchmakingService::class)->generateGroups($turnamen, 'random');

        $member = GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
            })
            ->first();

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-members/'.$member->id.'/scores', [
                'poin' => -5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.poin_didapat', -5);

        $this->assertSame(1, $member->fresh()->poinEntries()->count());
    }

    public function test_external_api_lists_active_mahjong_groups(): void
    {
        $turnamen = $this->prepareMahjongTournament(8);
        app(MahjongMatchmakingService::class)->generateGroups($turnamen, 'random');

        $response = $this->withHeaders($this->externalHeaders())
            ->getJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-groups')
            ->assertOk()
            ->assertJsonPath('success', true);

        $groups = $response->json('data.groups');
        $this->assertCount(2, $groups);
        $this->assertCount(4, $groups[0]['members']);
        $this->assertArrayHasKey('id_grup_member', $groups[0]['members'][0]);
    }

    public function test_external_api_lists_mahjong_and_mahjong_team_tournaments(): void
    {
        $mahjong = $this->prepareMahjongTournament(8);
        $mahjongTeam = Turnamen::create([
            'nama' => 'External Mahjong Team ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 16,
            'jenis' => 'mahjong_team',
            'status' => 'ongoing',
        ]);
        $padel = Turnamen::create([
            'nama' => 'External Padel ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'ongoing',
        ]);

        $response = $this->withHeaders($this->externalHeaders())
            ->getJson('/api/v1/external/tournaments/mahjong')
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mahjong->id, $ids);
        $this->assertContains($mahjongTeam->id, $ids);
        $this->assertNotContains($padel->id, $ids);

        $teamRow = collect($response->json('data'))->firstWhere('id', $mahjongTeam->id);
        $this->assertSame('mahjong_team', $teamRow['jenis']);
        $this->assertSame('Mahjong Tim', $teamRow['jenis_label']);

        $this->withHeaders($this->externalHeaders())
            ->getJson('/api/v1/external/tournaments/'.$mahjongTeam->id.'/participants')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($this->externalHeaders())
            ->getJson('/api/v1/external/tournaments/'.$mahjongTeam->id.'/group-standings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.turnamen.jenis', 'mahjong_team');
    }

    public function test_external_participants_include_mahjong_team_names(): void
    {
        $turnamen = Turnamen::create([
            'nama' => 'External Team Names ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => 16,
            'jenis' => 'mahjong_team',
            'players_per_group' => 4,
            'status' => 'open',
        ]);
        $turnamen->ensureDefaultKategori();

        $players = [];
        for ($i = 1; $i <= 4; $i++) {
            $players[] = [
                'nama' => "Named Team Player {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62823'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT).$i,
                'rating' => 2.5,
            ];
        }

        app(PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Dragon Squad',
            $players,
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            false,
            'approved'
        );

        $items = $this->withHeaders($this->externalHeaders())
            ->getJson('/api/v1/external/tournaments/'.$turnamen->id.'/participants')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.items');

        $this->assertNotEmpty($items);
        $this->assertSame('Dragon Squad', $items[0]['group_nama']);
        $this->assertNotNull($items[0]['group_id']);
    }

    public function test_external_api_lists_mahjong_team_tables_as_groups(): void
    {
        $turnamen = $this->prepareMahjongTeamTournament(16);
        app(MahjongTeamMatchmakingService::class)->generateTeams($turnamen, 'random');

        $response = $this->withHeaders($this->externalHeaders())
            ->getJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-groups')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.turnamen.jenis', 'mahjong_team');

        $groups = $response->json('data.groups');
        $this->assertCount(4, $groups);
        $this->assertCount(4, $groups[0]['members']);
        $this->assertArrayHasKey('id_grup_member', $groups[0]['members'][0]);
        $this->assertArrayHasKey('id_meja', $groups[0]);
        $this->assertSame($groups[0]['id'], $groups[0]['id_meja']);
        $this->assertNotEmpty($groups[0]['members'][0]['tim']);
    }

    public function test_external_api_stores_and_updates_mahjong_team_table_scores(): void
    {
        $turnamen = $this->prepareMahjongTeamTournament(16);
        app(MahjongTeamMatchmakingService::class)->generateTeams($turnamen, 'random');

        $meja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats.grupMember')
            ->first();

        $this->assertNotNull($meja);
        $this->assertCount(4, $meja->seats);

        $points = [8, -2, -3, -3];
        $scores = $meja->seats->values()->map(function ($seat, int $index) use ($points) {
            return [
                'id_grup_member' => (int) $seat->id_grup_member,
                'poin' => $points[$index],
            ];
        })->all();

        $winnerId = (int) $meja->seats->first()->id_grup_member;

        $store = $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-scores', [
                'id_grup' => $meja->id,
                'id_grup_member_pemenang' => $winnerId,
                'scores' => $scores,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.grup.id_meja', $meja->id);

        $this->assertCount(4, $store->json('data.members'));
        $this->assertSame(1, (int) collect($store->json('data.members'))->firstWhere('id_grup_member', $winnerId)['menang']);

        foreach ($scores as $score) {
            $member = GrupMember::findOrFail($score['id_grup_member']);
            $this->assertSame($score['poin'], (int) $member->poin_didapat);
            $this->assertSame(1, $member->poinEntries()->count());
            $this->assertSame((int) $member->id === $winnerId, (bool) $member->poinEntries()->first()->is_winner);
            $this->assertSame($meja->id, (int) $member->poinEntries()->first()->id_meja);
        }

        $firstMember = $meja->seats->first()->grupMember;
        $entry = $firstMember->fresh()->poinEntries()->first();
        $this->assertNotNull($entry);

        $this->withHeaders($this->externalHeaders())
            ->patchJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-scores/'.$entry->id, [
                'poin' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.poin_didapat', 12);

        $this->assertSame(12, (int) $entry->fresh()->poin);
        $this->assertSame(12, (int) $firstMember->fresh()->poin_didapat);
    }

    public function test_external_api_stores_mahjong_team_single_member_score(): void
    {
        $turnamen = $this->prepareMahjongTeamTournament(16);
        app(MahjongTeamMatchmakingService::class)->generateTeams($turnamen, 'random');

        $meja = TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with('seats')
            ->first();

        $member = GrupMember::findOrFail($meja->seats->first()->id_grup_member);

        $this->withHeaders($this->externalHeaders())
            ->postJson('/api/v1/external/tournaments/'.$turnamen->id.'/mahjong-members/'.$member->id.'/scores', [
                'poin' => -5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.poin_didapat', -5);

        $this->assertSame(1, $member->fresh()->poinEntries()->count());
        $this->assertSame($meja->id, (int) $member->fresh()->poinEntries()->first()->id_meja);
    }

    protected function prepareMahjongTournament(int $playerCount): Turnamen
    {
        $turnamen = Turnamen::create([
            'nama' => 'External Mahjong ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => $playerCount,
            'jenis' => 'mahjong',
            'status' => 'ongoing',
        ]);

        for ($i = 1; $i <= $playerCount; $i++) {
            $pemain = Pemain::create([
                'nama' => "External MJ {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62821' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT) . $i,
                'rating' => 2.5,
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

    protected function prepareMahjongTeamTournament(int $playerCount, int $playersPerTeam = 4): Turnamen
    {
        $turnamen = Turnamen::create([
            'nama' => 'External Mahjong Team ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => $playerCount,
            'jenis' => 'mahjong_team',
            'players_per_group' => $playersPerTeam,
            'status' => 'ongoing',
        ]);

        for ($i = 1; $i <= $playerCount; $i++) {
            $pemain = Pemain::create([
                'nama' => "External Team MJ {$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+62822' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT) . $i,
                'rating' => 2.5,
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

    /**
     * @return array<string, string>
     */
    protected function externalHeaders(): array
    {
        config(['external_api.key' => 'mahjong-score-test-key']);

        return [
            'X-API-Key' => 'mahjong-score-test-key',
            'Accept' => 'application/json',
        ];
    }
}
