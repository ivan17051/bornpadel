<?php

namespace Tests\Unit;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Services\MahjongTeamSeatingService;
use App\Services\MahjongTeamStandingRanker;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MahjongTeamSeatingAndRankerTest extends TestCase
{
    public function test_seat_plan_for_four_teams_is_one_from_each(): void
    {
        $teams = $this->fakeTeams(4);
        $plan = (new MahjongTeamSeatingService())->buildSeatPlan($teams);

        $this->assertCount(4, $plan);
        foreach ($plan as $table) {
            $this->assertCount(4, $table);
            $teamIds = [];
            foreach ($table as $memberId) {
                $teamIds[] = $this->teamIdForMember($teams, $memberId);
            }
            $this->assertCount(4, array_unique($teamIds));
        }
    }

    public function test_seat_plan_for_two_teams_is_two_and_two(): void
    {
        $teams = $this->fakeTeams(2);
        $plan = (new MahjongTeamSeatingService())->buildSeatPlan($teams);

        $this->assertCount(2, $plan);
        foreach ($plan as $table) {
            $counts = array_count_values(array_map(
                fn ($id) => $this->teamIdForMember($teams, $id),
                $table
            ));
            $this->assertSame([2, 2], array_values($counts));
        }
    }

    public function test_seat_plan_for_eight_teams_has_four_distinct_teams_per_table(): void
    {
        $teams = $this->fakeTeams(8);
        $plan = (new MahjongTeamSeatingService())->buildSeatPlan($teams);

        $this->assertCount(8, $plan);
        $seen = [];
        foreach ($plan as $table) {
            $teamIds = array_map(fn ($id) => $this->teamIdForMember($teams, $id), $table);
            $this->assertCount(4, array_unique($teamIds));
            foreach ($table as $memberId) {
                $this->assertArrayNotHasKey($memberId, $seen);
                $seen[$memberId] = true;
            }
        }
        $this->assertCount(32, $seen);
    }

    public function test_ranker_needs_tiebreak_and_accepts_picks(): void
    {
        $ranker = new MahjongTeamStandingRanker();
        $rows = new Collection([
            ['id_tim' => 1, 'nama' => 'A', 'total_poin' => 40],
            ['id_tim' => 2, 'nama' => 'B', 'total_poin' => 30],
            ['id_tim' => 3, 'nama' => 'C', 'total_poin' => 30],
            ['id_tim' => 4, 'nama' => 'D', 'total_poin' => 10],
        ]);

        $first = $ranker->resolveAdvanceTeams($rows, 2);
        $this->assertSame('needs_tiebreak', $first['status']);
        $this->assertSame(1, $first['slots_remaining']);
        $this->assertSame([1], $first['auto_qualified']->pluck('id_tim')->all());
        $this->assertSame([2, 3], $first['contested']->pluck('id_tim')->all());

        $resolved = $ranker->resolveAdvanceTeams($rows, 2, [3]);
        $this->assertSame('resolved', $resolved['status']);
        $this->assertSame([1, 3], $resolved['qualifiers']->pluck('id_tim')->all());
    }

    public function test_allowed_advance_counts(): void
    {
        $seating = new MahjongTeamSeatingService();
        $this->assertSame([4, 2, 1], $seating->allowedAdvanceCounts(8));
        $this->assertSame([2, 1], $seating->allowedAdvanceCounts(4));
        $this->assertSame([1], $seating->allowedAdvanceCounts(2));
    }

    /**
     * @return Collection<int, Grup>
     */
    protected function fakeTeams(int $count): Collection
    {
        $teams = collect();
        $memberId = 1;

        for ($t = 1; $t <= $count; $t++) {
            $grup = new Grup([
                'nama' => 'Tim '.$t,
                'is_aktif' => true,
            ]);
            $grup->id = $t;

            $members = collect();
            for ($p = 0; $p < 4; $p++) {
                $member = new GrupMember();
                $member->id = $memberId++;
                $member->id_grup = $t;
                $members->push($member);
            }

            $grup->setRelation('members', $members);
            $teams->push($grup);
        }

        return $teams;
    }

    protected function teamIdForMember(Collection $teams, int $memberId): int
    {
        foreach ($teams as $team) {
            if ($team->members->contains(fn ($m) => (int) $m->id === $memberId)) {
                return (int) $team->id;
            }
        }

        $this->fail('Member not found');
    }
}
