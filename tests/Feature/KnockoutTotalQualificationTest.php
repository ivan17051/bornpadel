<?php

namespace Tests\Feature;

use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KnockoutTotalQualificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_total_qualification_takes_top_n_per_group_then_best_remaining(): void
    {
        $turnamen = $this->createOngoingSingleTournament(20);
        $entries = $this->createApprovedEntries($turnamen, 20);
        $groups = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);
        $bracket = app(KnockoutBracketService::class);

        $groups->generateRandomGroups($turnamen, 4, 4);
        $groups->generateGroupMatches($turnamen->fresh());

        $this->scoreAllGroupMatchesDeterministically($turnamen, $scoring);

        $plan = $bracket->describeTotalQualification($turnamen->fresh(), 16);

        $this->assertSame(5, $plan['group_count']);
        $this->assertSame(20, $plan['participant_count']);
        $this->assertSame(3, $plan['base_per_group']);
        $this->assertSame(1, $plan['lucky_loser_slots']);

        $qualifiers = $bracket->getQualifiersByTotal($turnamen->fresh(), 16);

        $this->assertCount(16, $qualifiers);
        $this->assertSame(15, $qualifiers->where('qualification_path', 'automatic')->count());
        $this->assertSame(1, $qualifiers->where('qualification_path', 'lucky_loser')->count());

        // Every group should contribute at least its top 3.
        $byGroup = $qualifiers->groupBy('grup');
        $this->assertCount(5, $byGroup);
        foreach ($byGroup as $groupRows) {
            $this->assertGreaterThanOrEqual(3, $groupRows->count());
        }

        $lucky = $qualifiers->firstWhere('qualification_path', 'lucky_loser');
        $this->assertNotNull($lucky);
        $this->assertSame(4, (int) $lucky['rank']);

        // Lucky loser must be the best 4th by standing stats among all 4ths.
        $standings = app(\App\Services\LeaderboardService::class)->getStandings($turnamen->id);
        $fourths = $standings->flatMap(fn (array $grup) => $grup['standings']->where('rank', 4))->values();
        $bestFourth = $fourths->sort(fn ($a, $b) => GrupMember::comparePadelStandingRows($a, $b))->first();

        $this->assertSame($bestFourth['id_peserta'], $lucky['id_peserta']);
        $this->assertContains($entries->first()->id, $entries->pluck('id')); // sanity: entries created
    }

    public function test_generate_knockout_bracket_with_total_mode(): void
    {
        $turnamen = $this->createOngoingSingleTournament(20);
        $this->createApprovedEntries($turnamen, 20);
        $groups = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);
        $bracket = app(KnockoutBracketService::class);

        $groups->generateRandomGroups($turnamen, 4, 4);
        $groups->generateGroupMatches($turnamen->fresh());
        $this->scoreAllGroupMatchesDeterministically($turnamen, $scoring);

        $result = $bracket->generateKnockoutBracket(
            $turnamen->fresh(),
            16,
            KnockoutBracketService::QUALIFICATION_TOTAL
        );

        $this->assertSame('total', $result['qualification_mode']);
        $this->assertSame(16, $result['qualifiers']);
        $this->assertSame(16, $result['bracket_size']);
        $this->assertSame(0, $result['bye_count']);
        $this->assertSame(1, $result['lucky_loser_slots']);
        $this->assertStringContainsString('top 3', $result['qualification_summary']);
    }

    protected function scoreAllGroupMatchesDeterministically(Turnamen $turnamen, MatchScoringService $scoring): void
    {
        $matches = $turnamen->pertandingan()
            ->whereNotNull('id_grup')
            ->with(['peserta1.pemain1', 'peserta2.pemain1'])
            ->orderBy('id')
            ->get();

        foreach ($matches as $index => $match) {
            // Vary margins so 4th places are distinguishable by GD.
            $margin = 1 + ($index % 4);
            $scoring->recordScore($match->fresh(), [
                ['skor_pemain1' => 6, 'skor_pemain2' => max(0, 6 - $margin)],
                ['skor_pemain1' => 6, 'skor_pemain2' => max(0, 5 - ($index % 3))],
            ]);
        }
    }

    protected function createOngoingSingleTournament(int $capacity = 20): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Total Qualifier Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => $capacity,
            'jenis' => 'single',
            'status' => 'ongoing',
            'group_matches_generated_at' => null,
        ]);
    }

    protected function createApprovedEntries(Turnamen $turnamen, int $count)
    {
        return collect(range(1, $count))->map(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "TQ Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0815' . str_pad((string) $index . random_int(10, 99), 8, '0', STR_PAD_LEFT),
                'rating' => 2 + ($index / 10),
                'total_poin' => 0,
            ]);

            return TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        });
    }
}
