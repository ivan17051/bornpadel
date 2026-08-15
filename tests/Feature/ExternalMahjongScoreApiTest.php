<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\MahjongMatchmakingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
                'scores' => [
                    ['id_grup_member' => 1, 'poin' => 1],
                    ['id_grup_member' => 2, 'poin' => 1],
                    ['id_grup_member' => 3, 'poin' => 1],
                    ['id_grup_member' => 4, 'poin' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Endpoint ini hanya tersedia untuk turnamen Mahjong.');
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
                'scores' => $scores,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertCount(4, $store->json('data.members'));

        foreach ($scores as $score) {
            $member = GrupMember::findOrFail($score['id_grup_member']);
            $this->assertSame($score['poin'], (int) $member->poin_didapat);
            $this->assertSame(1, $member->poinEntries()->count());
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
