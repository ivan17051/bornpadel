<?php

namespace Tests\Feature;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPasangan;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\LeaderboardService;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PostLeagueRankingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_post_league_bands_by_group_place_and_sorts_within_band(): void
    {
        $turnamen = $this->createOngoingSingle(4);

        $grupA = $this->createGrup($turnamen, 'Grup A');
        $grupB = $this->createGrup($turnamen, 'Grup B');

        $peserta = TurnamenPeserta::query()->forTurnamen($turnamen->id)->orderBy('id')->get()->values();

        // A1 strongest overall among 1sts, B1 weaker 1st
        $this->addMember($grupA, $peserta[0], 6, 4, 10);
        $this->addMember($grupA, $peserta[1], 2, 1, -4);
        $this->addMember($grupB, $peserta[2], 4, 3, 5);
        $this->addMember($grupB, $peserta[3], 0, 0, -8);

        $ranking = app(LeaderboardService::class)->getPostLeagueRanking($turnamen->id);

        $this->assertFalse($ranking['has_bracket']);
        $this->assertCount(2, $ranking['sections']);
        $this->assertSame('Juara 1', $ranking['sections'][0]['label']);
        $this->assertSame('Juara 2', $ranking['sections'][1]['label']);

        $firsts = $ranking['sections'][0]['rows'];
        $this->assertCount(2, $firsts);
        $this->assertSame(1, $firsts[0]['overall_rank']);
        $this->assertSame(6, $firsts[0]['poin_didapat']);
        $this->assertSame('Grup A', $firsts[0]['grup']);
        $this->assertSame(2, $firsts[1]['overall_rank']);
        $this->assertSame(4, $firsts[1]['poin_didapat']);
        $this->assertSame('Grup B', $firsts[1]['grup']);
        $this->assertFalse($firsts[0]['advances']);
        $this->assertFalse($firsts[1]['advances']);

        $seconds = $ranking['sections'][1]['rows'];
        $this->assertSame(3, $seconds[0]['overall_rank']);
        $this->assertSame(2, $seconds[0]['poin_didapat']);
        $this->assertSame(4, $seconds[1]['overall_rank']);
        $this->assertSame(0, $seconds[1]['poin_didapat']);
    }

    public function test_advances_true_only_after_knockout_bracket(): void
    {
        $turnamen = Turnamen::create([
            'nama' => 'Post League KO ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'ongoing',
        ]);

        collect(range(1, 8))->each(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "PL Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0816' . str_pad((string) ($index . random_int(10, 99)), 8, '0', STR_PAD_LEFT),
                'rating' => 3 + ($index / 10),
            ]);

            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        });

        $peserta = TurnamenPeserta::query()->forTurnamen($turnamen->id)->orderBy('id')->get();
        foreach ($peserta->chunk(2) as $pair) {
            $pair = $pair->values();
            TurnamenPasangan::create([
                'id_turnamen' => $turnamen->id,
                'id_peserta_1' => $pair[0]->id,
                'id_peserta_2' => $pair[1]->id,
                'paired_at' => now(),
            ]);
        }
        $turnamen->update(['registration_paired_at' => now()]);

        $groups = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);
        $bracket = app(KnockoutBracketService::class);
        $leaderboard = app(LeaderboardService::class);

        $groups->generateRandomGroups($turnamen->fresh(), 2, 2);
        $groups->generateGroupMatches($turnamen->fresh());

        foreach ($turnamen->pertandingan()->whereNotNull('id_grup')->get() as $match) {
            $scoring->recordScore($match->fresh(), [
                ['skor_pemain1' => 6, 'skor_pemain2' => 1],
                ['skor_pemain1' => 6, 'skor_pemain2' => 2],
            ]);
        }

        $before = $leaderboard->getPostLeagueRanking($turnamen->id);
        $this->assertFalse($before['has_bracket']);
        $this->assertTrue(
            $before['sections']->flatMap(fn ($s) => $s['rows'])->every(fn ($row) => $row['advances'] === false)
        );

        $bracket->generateKnockoutBracket($turnamen->fresh(), 1);

        $after = $leaderboard->getPostLeagueRanking($turnamen->id);
        $this->assertTrue($after['has_bracket']);

        $advancing = $after['sections']->flatMap(fn ($s) => $s['rows'])->filter(fn ($row) => $row['advances']);
        $this->assertGreaterThanOrEqual(2, $advancing->count());

        // With 1 lolos per group, all advances should come from Juara 1 band.
        $this->assertTrue($advancing->every(fn ($row) => (int) $row['group_rank'] === 1));
    }

    public function test_guest_standings_page_includes_post_league_for_single(): void
    {
        $turnamen = $this->createOngoingSingle(4);
        $grup = $this->createGrup($turnamen, 'Grup A');
        $peserta = TurnamenPeserta::query()->forTurnamen($turnamen->id)->orderBy('id')->get()->values();
        $this->addMember($grup, $peserta[0], 2, 2, 3);
        $this->addMember($grup, $peserta[1], 0, 0, -3);

        $grupB = $this->createGrup($turnamen, 'Grup B');
        $this->addMember($grupB, $peserta[2], 2, 1, 1);
        $this->addMember($grupB, $peserta[3], 0, 0, -1);

        $response = $this->get(route('guest.standings', ['id_turnamen' => $turnamen->id]));
        $response->assertOk();
        $response->assertSee('Peringkat Lintas Grup');
        $response->assertSee('Juara 1');
        $response->assertSee('Juara 2');
    }

    protected function createOngoingSingle(int $playerCount): Turnamen
    {
        $turnamen = Turnamen::create([
            'nama' => 'Post League Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => $playerCount,
            'jenis' => 'single',
            'status' => 'ongoing',
        ]);

        for ($i = 1; $i <= $playerCount; $i++) {
            $pemain = Pemain::create([
                'nama' => "PL Solo {$i} " . uniqid(),
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '0815' . str_pad((string) ($i . random_int(100, 999)), 8, '0', STR_PAD_LEFT),
                'rating' => 2.5 + $i * 0.1,
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

    protected function createGrup(Turnamen $turnamen, string $nama): Grup
    {
        return Grup::create([
            'id_turnamen' => $turnamen->id,
            'nama' => $nama,
            'babak' => 1,
            'ronde' => 1,
            'is_aktif' => true,
            'poin_didapat' => 0,
            'set_menang' => 0,
            'game_menang' => 0,
        ]);
    }

    protected function addMember(
        Grup $grup,
        TurnamenPeserta $peserta,
        int $poin,
        int $set,
        int $gd
    ): GrupMember {
        return GrupMember::create([
            'id_grup' => $grup->id,
            'id_pemain' => $peserta->id_pemain1,
            'id_turnamen_peserta' => $peserta->id,
            'poin_didapat' => $poin,
            'poin_akumulasi' => 0,
            'set_menang' => $set,
            'games_menang' => $gd,
        ]);
    }
}
