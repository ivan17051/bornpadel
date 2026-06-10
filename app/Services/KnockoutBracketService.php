<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KnockoutBracketService
{
    protected $leaderboardService;

    public function __construct(LeaderboardService $leaderboardService)
    {
        $this->leaderboardService = $leaderboardService;
    }

    public function hasKnockoutBracket(Turnamen $turnamen): bool
    {
        return Pertandingan::where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->whereIn('nama_ronde', ['Perempatfinal', 'Semifinal', 'Final'])
            ->exists();
    }

    public function canEndGroupStage(Turnamen $turnamen): bool
    {
        if (! $turnamen->grup()->exists()) {
            return false;
        }

        if ($this->hasKnockoutBracket($turnamen)) {
            return false;
        }

        $groupMatches = Pertandingan::where('id_turnamen', $turnamen->id)
            ->where('nama_ronde', 'Fase Grup');

        $total = $groupMatches->count();
        $completed = (clone $groupMatches)->where('status', 'completed')->count();

        return $total > 0 && $total === $completed;
    }

    public function getQualifiers(Turnamen $turnamen): Collection
    {
        $standings = $this->leaderboardService->getStandings($turnamen->id);

        return $standings->flatMap(function ($grup) {
            return $grup['standings']->take(2)->map(function ($row) use ($grup) {
                return [
                    'id_pemain' => $row['id_pemain'],
                    'nama' => $row['nama'],
                    'grup' => $grup['nama'],
                    'rank' => $row['rank'],
                    'poin_didapat' => $row['poin_didapat'],
                    'set_menang' => $row['set_menang'],
                    'games_menang' => $row['games_menang'],
                ];
            });
        })->values();
    }

    public function generateKnockoutBracket(Turnamen $turnamen): array
    {
        if (! $this->canEndGroupStage($turnamen)) {
            throw new RuntimeException('Fase grup belum selesai atau bracket knockout sudah dibuat.');
        }

        $qualifiers = $this->getQualifiers($turnamen);
        $groupCount = $turnamen->grup()->count();

        if ($qualifiers->count() < 2) {
            throw new RuntimeException('Minimal 2 pemain lolos diperlukan untuk bracket knockout.');
        }

        return DB::transaction(function () use ($turnamen, $qualifiers, $groupCount) {
            $firstRoundPairs = $this->buildCrossGroupPairings($qualifiers, $groupCount);
            $rounds = $this->determineRounds($qualifiers->count(), $groupCount);

            return $this->createBracketStructure($turnamen, $rounds, $firstRoundPairs, $qualifiers);
        });
    }

    protected function determineRounds(int $qualifierCount, int $groupCount): array
    {
        if ($qualifierCount <= 2 || $groupCount <= 1) {
            return ['Final'];
        }

        if ($qualifierCount <= 4 || $groupCount <= 2) {
            return ['Semifinal', 'Final'];
        }

        return ['Perempatfinal', 'Semifinal', 'Final'];
    }

    protected function buildCrossGroupPairings(Collection $qualifiers, int $groupCount): array
    {
        if ($groupCount === 1) {
            $top = $qualifiers->take(2)->values();

            return $top->count() === 2 ? [[$top[0], $top[1]]] : [];
        }

        $byGroup = $qualifiers->groupBy('grup')->values();
        $pairs = [];

        for ($i = 0; $i < $groupCount; $i++) {
            $first = $byGroup[$i][0] ?? null;
            $secondGroupIndex = ($i + 1) % $groupCount;
            $second = $byGroup[$secondGroupIndex][1] ?? null;

            if ($first && $second) {
                $pairs[] = [$first, $second];
            }
        }

        return $pairs;
    }

    protected function createBracketStructure(
        Turnamen $turnamen,
        array $rounds,
        array $firstRoundPairs,
        Collection $qualifiers
    ): array {
        $created = [];
        $roundMatches = [];

        foreach (array_reverse($rounds) as $roundName) {
            $matchCount = $this->matchCountForRound($roundName, count($firstRoundPairs), $rounds);
            $roundMatches[$roundName] = [];

            for ($i = 0; $i < $matchCount; $i++) {
                $match = Pertandingan::create([
                    'id_turnamen' => $turnamen->id,
                    'id_grup' => null,
                    'nama_ronde' => $roundName,
                    'id_pemain1' => null,
                    'id_pemain2' => null,
                    'status' => 'scheduled',
                ]);
                $roundMatches[$roundName][] = $match;
                $created[] = $match;
            }
        }

        $this->linkRounds($roundMatches, $rounds);
        $this->assignFirstRoundPlayers($roundMatches, $rounds, $firstRoundPairs, $qualifiers);

        return [
            'rounds' => $rounds,
            'matches_created' => count($created),
            'qualifiers' => $qualifiers->count(),
        ];
    }

    protected function matchCountForRound(string $roundName, int $firstRoundPairCount, array $rounds): int
    {
        $firstRound = $rounds[0];

        if ($roundName === $firstRound) {
            return max(1, $firstRoundPairCount);
        }

        if ($roundName === 'Final') {
            return 1;
        }

        if ($roundName === 'Semifinal') {
            return $firstRound === 'Perempatfinal' ? 2 : max(1, $firstRoundPairCount);
        }

        return $firstRoundPairCount;
    }

    protected function linkRounds(array $roundMatches, array $rounds): void
    {
        for ($r = 0; $r < count($rounds) - 1; $r++) {
            $currentRound = $rounds[$r];
            $nextRound = $rounds[$r + 1];
            $current = $roundMatches[$currentRound];
            $next = $roundMatches[$nextRound];

            foreach ($current as $index => $match) {
                $nextIndex = (int) floor($index / 2);
                if (isset($next[$nextIndex])) {
                    $match->update(['id_next_pertandingan' => $next[$nextIndex]->id]);
                }
            }
        }
    }

    protected function assignFirstRoundPlayers(
        array $roundMatches,
        array $rounds,
        array $firstRoundPairs,
        Collection $qualifiers
    ): void {
        $firstRound = $rounds[0];
        $matches = $roundMatches[$firstRound];

        if ($firstRound === 'Final' && count($firstRoundPairs) === 0) {
            $top = $qualifiers->take(2);
            if ($matches[0] && $top->count() === 2) {
                $matches[0]->update([
                    'id_pemain1' => $top[0]['id_pemain'],
                    'id_pemain2' => $top[1]['id_pemain'],
                ]);
            }

            return;
        }

        foreach ($firstRoundPairs as $index => $pair) {
            if (! isset($matches[$index])) {
                break;
            }

            $matches[$index]->update([
                'id_pemain1' => $pair[0]['id_pemain'],
                'id_pemain2' => $pair[1]['id_pemain'],
            ]);
        }

        if ($firstRound === 'Perempatfinal' && $qualifiers->count() > count($firstRoundPairs) * 2) {
            $this->assignByesToSemifinal($roundMatches, $qualifiers, $firstRoundPairs);
        }
    }

    protected function assignByesToSemifinal(array $roundMatches, Collection $qualifiers, array $pairs): void
    {
        if (! isset($roundMatches['Semifinal'])) {
            return;
        }

        $playingIds = collect($pairs)->flatten(1)->pluck('id_pemain');
        $byePlayers = $qualifiers->whereNotIn('id_pemain', $playingIds)
            ->sortByDesc('poin_didapat')
            ->take(2)
            ->values();

        foreach ($byePlayers as $index => $bye) {
            if (isset($roundMatches['Semifinal'][$index])) {
                $roundMatches['Semifinal'][$index]->update([
                    'id_pemain1' => $bye['id_pemain'],
                ]);
            }
        }
    }

    public function getBracketTree(Turnamen $turnamen): array
    {
        $roundOrder = ['Perempatfinal', 'Semifinal', 'Final'];
        $result = [];

        foreach ($roundOrder as $round) {
            $matches = Pertandingan::where('id_turnamen', $turnamen->id)
                ->whereNull('id_grup')
                ->where('nama_ronde', $round)
                ->with(['pemain1', 'pemain2', 'pemenang', 'skor'])
                ->orderBy('id')
                ->get();

            if ($matches->isEmpty()) {
                continue;
            }

            $result[] = [
                'nama_ronde' => $round,
                'matches' => $matches->map(function (Pertandingan $m) {
                    return $this->formatMatchForBracket($m);
                })->values()->all(),
            ];
        }

        return $result;
    }

    public function formatMatchForBracket(Pertandingan $m): array
    {
        $skorSummary = $m->skor->map(function ($s) {
            return $s->skor_pemain1 . '-' . $s->skor_pemain2;
        })->implode(', ');

        return [
            'id' => $m->id,
            'pemain1' => $m->pemain1 ? $m->pemain1->nama : 'TBD',
            'pemain2' => $m->pemain2 ? $m->pemain2->nama : 'TBD',
            'pemain1_id' => $m->id_pemain1,
            'pemain2_id' => $m->id_pemain2,
            'pemenang' => $m->pemenang ? $m->pemenang->nama : null,
            'pemenang_id' => $m->id_pemenang,
            'status' => $m->status,
            'skor' => $skorSummary,
            'id_next_pertandingan' => $m->id_next_pertandingan,
        ];
    }

    public function advanceWinner(Pertandingan $pertandingan, int $winnerId): void
    {
        if ($pertandingan->id_grup || ! $pertandingan->id_next_pertandingan) {
            return;
        }

        $next = Pertandingan::find($pertandingan->id_next_pertandingan);

        if (! $next) {
            return;
        }

        $feeders = Pertandingan::where('id_next_pertandingan', $next->id)
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $slot = $feeders->search($pertandingan->id);

        if ($slot === 0) {
            $next->update(['id_pemain1' => $winnerId]);
        } elseif ($slot === 1) {
            $next->update(['id_pemain2' => $winnerId]);
        }
    }
}
