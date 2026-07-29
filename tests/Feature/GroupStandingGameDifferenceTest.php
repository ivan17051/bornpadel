<?php

namespace Tests\Feature;

use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPasangan;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GroupStandingGameDifferenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_group_score_stores_game_difference_not_games_won(): void
    {
        $turnamen = $this->createOngoingSingleTournament();
        $this->createApprovedEntries($turnamen, 4);
        $service = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);

        $service->generateRandomGroups($turnamen, 2, 2);
        $service->generateGroupMatches($turnamen->fresh());

        $match = $turnamen->pertandingan()->whereNotNull('id_grup')->orderBy('id')->first();
        $this->assertNotNull($match);

        // Winner games 12, loser games 5 => GD +7 / -7
        $scoring->recordScore($match, [
            ['skor_pemain1' => 6, 'skor_pemain2' => 2],
            ['skor_pemain1' => 6, 'skor_pemain2' => 3],
        ]);

        $match = $match->fresh();
        $winnerId = (int) $match->id_pemenang;
        $loserId = $winnerId === (int) $match->id_pemain1
            ? (int) $match->id_pemain2
            : (int) $match->id_pemain1;

        $winner = GrupMember::where('id_grup', $match->id_grup)->where('id_pemain', $winnerId)->first();
        $loser = GrupMember::where('id_grup', $match->id_grup)->where('id_pemain', $loserId)->first();

        $this->assertSame(2, (int) $winner->poin_didapat);
        $this->assertSame(2, (int) $winner->set_menang);
        $this->assertSame(7, (int) $winner->games_menang);
        $this->assertSame(0, (int) $loser->set_menang);
        $this->assertSame(-7, (int) $loser->games_menang);
        $this->assertSame('+7', GrupMember::formatGameDifference($winner->games_menang));
        $this->assertSame('-7', GrupMember::formatGameDifference($loser->games_menang));
    }

    public function test_recalculate_rebuilds_game_difference_from_match_scores(): void
    {
        $turnamen = $this->createOngoingSingleTournament();
        $this->createApprovedEntries($turnamen, 4);
        $service = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);

        $service->generateRandomGroups($turnamen, 2, 2);
        $service->generateGroupMatches($turnamen->fresh());

        $match = $turnamen->pertandingan()->whereNotNull('id_grup')->orderBy('id')->first();
        $scoring->recordScore($match, [
            ['skor_pemain1' => 6, 'skor_pemain2' => 4],
            ['skor_pemain1' => 6, 'skor_pemain2' => 1],
        ]);

        // Corrupt stored GD as if old games-won logic was used
        GrupMember::where('id_grup', $match->id_grup)->update(['games_menang' => 99]);

        $scoring->recalculateGroupStandingsForGrup((int) $match->id_grup);

        $match = $match->fresh();
        $winnerId = (int) $match->id_pemenang;
        $loserId = $winnerId === (int) $match->id_pemain1
            ? (int) $match->id_pemain2
            : (int) $match->id_pemain1;

        $winner = GrupMember::where('id_grup', $match->id_grup)->where('id_pemain', $winnerId)->first();
        $loser = GrupMember::where('id_grup', $match->id_grup)->where('id_pemain', $loserId)->first();

        // 12-5 => +7 / -7
        $this->assertSame(7, (int) $winner->games_menang);
        $this->assertSame(-7, (int) $loser->games_menang);
        $this->assertSame(2, (int) $winner->poin_didapat);
    }

    protected function createOngoingSingleTournament(): Turnamen
    {
        return Turnamen::create([
            'nama' => 'GD Standing Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'ongoing',
            'group_matches_generated_at' => null,
        ]);
    }

    protected function createApprovedEntries(Turnamen $turnamen, int $count)
    {
        $entries = collect(range(1, $count))->map(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "GD Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0814' . str_pad((string) $index . random_int(10, 99), 8, '0', STR_PAD_LEFT),
                'rating' => 3 + ($index / 10),
                'total_poin' => 0,
            ]);

            return TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        });

        foreach ($entries->chunk(2) as $pair) {
            $pair = $pair->values();
            TurnamenPasangan::create([
                'id_turnamen' => $turnamen->id,
                'id_peserta_1' => $pair[0]->id,
                'id_peserta_2' => $pair[1]->id,
                'paired_at' => now(),
            ]);
        }

        $turnamen->update(['registration_paired_at' => now()]);

        return $entries;
    }
}
