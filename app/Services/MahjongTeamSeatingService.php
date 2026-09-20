<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use Illuminate\Support\Collection;
use RuntimeException;

class MahjongTeamSeatingService
{
    public const TABLE_SIZE = 4;

    public const PLAYERS_PER_TEAM = 4;

    /** @var list<int> */
    public const ALLOWED_TEAM_COUNTS = [8, 4, 2];

    /**
     * Build seat assignments: list of tables, each a list of GrupMember ids.
     *
     * @param  \Illuminate\Support\Collection<int, Grup>  $teams
     * @return list<list<int>>
     */
    public function buildSeatPlan(Collection $teams): array
    {
        $teamCount = $teams->count();

        if (! in_array($teamCount, self::ALLOWED_TEAM_COUNTS, true)) {
            throw new RuntimeException('Jumlah tim aktif harus 8, 4, atau 2 untuk membentuk meja.');
        }

        $teams = $teams->values();

        foreach ($teams as $team) {
            $memberCount = $team->relationLoaded('members')
                ? $team->members->count()
                : $team->members()->count();

            if ($memberCount < self::TABLE_SIZE) {
                throw new RuntimeException(sprintf(
                    'Setiap tim harus berisi minimal %d pemain.',
                    self::TABLE_SIZE
                ));
            }
        }

        $shuffledMembers = $teams->map(function (Grup $team) {
            $members = $team->relationLoaded('members')
                ? $team->members
                : $team->members()->get();

            return $members->shuffle()->values()->take(self::TABLE_SIZE)->values();
        })->values();

        if ($teamCount === 8) {
            $plan = $this->seatEightTeams($shuffledMembers);
        } elseif ($teamCount === 4) {
            $plan = $this->seatFourTeams($shuffledMembers);
        } else {
            $plan = $this->seatTwoTeams($shuffledMembers);
        }

        $this->assertValidPlan($plan, $teams);

        return $plan;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, GrupMember>>  $teamsMembers
     * @return list<list<int>>
     */
    protected function seatEightTeams(Collection $teamsMembers): array
    {
        $tables = [];

        for ($t = 0; $t < 8; $t++) {
            $seatIds = [];

            for ($k = 0; $k < self::TABLE_SIZE; $k++) {
                $teamIndex = ($t + $k) % 8;
                $seatIds[] = (int) $teamsMembers[$teamIndex][$k]->id;
            }

            shuffle($seatIds);
            $tables[] = $seatIds;
        }

        return $tables;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, GrupMember>>  $teamsMembers
     * @return list<list<int>>
     */
    protected function seatFourTeams(Collection $teamsMembers): array
    {
        $tables = [];

        for ($t = 0; $t < 4; $t++) {
            $seatIds = [];

            for ($teamIndex = 0; $teamIndex < 4; $teamIndex++) {
                $seatIds[] = (int) $teamsMembers[$teamIndex][$t]->id;
            }

            shuffle($seatIds);
            $tables[] = $seatIds;
        }

        return $tables;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, GrupMember>>  $teamsMembers
     * @return list<list<int>>
     */
    protected function seatTwoTeams(Collection $teamsMembers): array
    {
        $a = $teamsMembers[0];
        $b = $teamsMembers[1];

        $tables = [
            [
                (int) $a[0]->id,
                (int) $a[1]->id,
                (int) $b[0]->id,
                (int) $b[1]->id,
            ],
            [
                (int) $a[2]->id,
                (int) $a[3]->id,
                (int) $b[2]->id,
                (int) $b[3]->id,
            ],
        ];

        foreach ($tables as &$table) {
            shuffle($table);
        }
        unset($table);

        return $tables;
    }

    /**
     * @param  list<list<int>>  $plan
     * @param  \Illuminate\Support\Collection<int, Grup>  $teams
     */
    public function assertValidPlan(array $plan, Collection $teams): void
    {
        $teamCount = $teams->count();
        $memberToTeam = [];

        foreach ($teams as $team) {
            $members = $team->relationLoaded('members') ? $team->members : $team->members()->get();
            foreach ($members as $member) {
                $memberToTeam[(int) $member->id] = (int) $team->id;
            }
        }

        $seenMembers = [];

        foreach ($plan as $tableIndex => $seatIds) {
            if (count($seatIds) !== self::TABLE_SIZE) {
                throw new RuntimeException('Setiap meja harus berisi 4 pemain.');
            }

            $teamIdsAtTable = [];

            foreach ($seatIds as $memberId) {
                $memberId = (int) $memberId;

                if (isset($seenMembers[$memberId])) {
                    throw new RuntimeException('Pemain tidak boleh duduk di dua meja sekaligus.');
                }

                $seenMembers[$memberId] = true;
                $teamId = $memberToTeam[$memberId] ?? null;

                if ($teamId === null) {
                    throw new RuntimeException('Kursi meja mereferensikan pemain di luar tim aktif.');
                }

                $teamIdsAtTable[] = $teamId;
            }

            $counts = array_count_values($teamIdsAtTable);
            $expectedPerTeam = (int) (self::TABLE_SIZE / $teamCount);

            if ($teamCount === 8) {
                // 4 distinct teams, one seat each.
                if (count($counts) !== self::TABLE_SIZE || max($counts) !== 1) {
                    throw new RuntimeException(sprintf(
                        'Meja %d harus berisi 4 pemain dari 4 tim berbeda.',
                        $tableIndex + 1
                    ));
                }
            } else {
                foreach ($counts as $count) {
                    if ($count !== $expectedPerTeam) {
                        throw new RuntimeException(sprintf(
                            'Meja %d harus berisi tepat %d pemain per tim.',
                            $tableIndex + 1,
                            $expectedPerTeam
                        ));
                    }
                }

                if (count($counts) !== $teamCount) {
                    throw new RuntimeException(sprintf(
                        'Meja %d harus mencakup semua tim aktif.',
                        $tableIndex + 1
                    ));
                }
            }
        }

        if (count($seenMembers) !== $teamCount * self::TABLE_SIZE) {
            throw new RuntimeException('Setiap tim aktif harus mendapat 4 kursi di meja.');
        }
    }

    /**
     * @return list<int>
     */
    public function allowedAdvanceCounts(int $currentTeamCount): array
    {
        if ($currentTeamCount <= 1) {
            return [1];
        }

        $allowed = array_values(array_filter(
            self::ALLOWED_TEAM_COUNTS,
            fn (int $n) => $n < $currentTeamCount
        ));
        $allowed[] = 1;

        return array_values(array_unique($allowed));
    }
}
