<?php



namespace App\Services;



use App\Models\Pemain;

use App\Models\GrupMember;

use App\Models\Pertandingan;

use App\Models\Turnamen;

use App\Models\TurnamenPeserta;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use RuntimeException;



class KnockoutBracketService

{

    protected $leaderboardService;

    protected $pointRewardService;

    public function __construct(
        LeaderboardService $leaderboardService,
        PointRewardService $pointRewardService
    ) {
        $this->leaderboardService = $leaderboardService;
        $this->pointRewardService = $pointRewardService;
    }



    public function hasKnockoutBracket(Turnamen $turnamen): bool
    {
        return Pertandingan::where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->whereIn('nama_ronde', ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final'])
            ->exists();
    }

    /**
     * Participant keys that appear in any knockout fixture (for post-league advance highlight).
     *
     * @return array{peserta_ids: array<int, int>, pemain_ids: array<int, int>}
     */
    public function getKnockoutParticipantKeys(Turnamen $turnamen): array
    {
        $pesertaIds = [];
        $pemainIds = [];

        $matches = $this->knockoutMatchesQuery($turnamen)
            ->get([
                'id_peserta1',
                'id_peserta2',
                'id_pemain1',
                'id_pemain2',
                'id_pemain1_partner',
                'id_pemain2_partner',
            ]);

        foreach ($matches as $match) {
            foreach ([(int) $match->id_peserta1, (int) $match->id_peserta2] as $pesertaId) {
                if ($pesertaId > 0) {
                    $pesertaIds[$pesertaId] = $pesertaId;
                }
            }

            foreach ([
                (int) $match->id_pemain1,
                (int) $match->id_pemain2,
                (int) $match->id_pemain1_partner,
                (int) $match->id_pemain2_partner,
            ] as $pemainId) {
                if ($pemainId > 0) {
                    $pemainIds[$pemainId] = $pemainId;
                }
            }
        }

        return [
            'peserta_ids' => array_values($pesertaIds),
            'pemain_ids' => array_values($pemainIds),
        ];
    }



    /**
     * @return list<string>
     */
    public function knockoutRoundNames(): array
    {
        return ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final', 'Perebutan Juara 3'];
    }

    protected function knockoutMatchesQuery(Turnamen $turnamen)
    {
        return Pertandingan::query()
            ->where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->whereIn('nama_ronde', $this->knockoutRoundNames());
    }

    public function canResetKnockoutBracket(Turnamen $turnamen): bool
    {
        if ($turnamen->isMahjong() || $turnamen->status === 'completed') {
            return false;
        }

        return $this->knockoutMatchesQuery($turnamen)->exists();
    }

    public function hasKnockoutScores(Turnamen $turnamen): bool
    {
        return $this->knockoutMatchesQuery($turnamen)
            ->where(function ($query) {
                $query->where('status', 'completed')
                    ->orWhereHas('skor');
            })
            ->exists();
    }

    /**
     * @return array{deleted: int, revoked_wins: int, had_scores: bool}
     */
    public function resetKnockoutBracket(Turnamen $turnamen): array
    {
        if (! $this->canResetKnockoutBracket($turnamen)) {
            throw new RuntimeException(
                'Reset bracket hanya tersedia saat turnamen ongoing dan bracket sudah dibuat.'
            );
        }

        return DB::transaction(function () use ($turnamen) {
            $locked = Turnamen::query()->lockForUpdate()->findOrFail($turnamen->id);

            if ($locked->status === 'completed') {
                throw new RuntimeException('Turnamen sudah selesai dan tidak dapat direset bracket.');
            }

            $matches = $this->knockoutMatchesQuery($locked)
                ->with(['skor'])
                ->orderBy('id')
                ->get();

            if ($matches->isEmpty()) {
                return [
                    'deleted' => 0,
                    'revoked_wins' => 0,
                    'had_scores' => false,
                ];
            }

            $hadScores = false;
            $revokedWins = 0;

            foreach ($matches as $match) {
                $hasScore = $match->status === 'completed' || $match->skor->isNotEmpty();

                if (! $hasScore) {
                    continue;
                }

                $hadScores = true;

                if ($match->id_pemenang || $match->id_peserta_pemenang) {
                    $this->pointRewardService->revokeMatchWin(
                        $match,
                        $match->id_peserta_pemenang ? (int) $match->id_peserta_pemenang : null,
                        $match->id_pemenang ? (int) $match->id_pemenang : null
                    );
                    $revokedWins++;
                }
            }

            $ids = $matches->pluck('id');

            Pertandingan::whereIn('id', $ids)->update([
                'id_next_pertandingan' => null,
                'id_next_pertandingan_kalah' => null,
            ]);

            Pertandingan::whereIn('id', $ids)->delete();

            return [
                'deleted' => $ids->count(),
                'revoked_wins' => $revokedWins,
                'had_scores' => $hadScores,
            ];
        });
    }
    public function canEndGroupStage(Turnamen $turnamen): bool

    {

        if (! $turnamen->grup()->exists()) {

            return false;

        }

        if ($turnamen->isMahjong()) {

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



    public const QUALIFICATION_PER_GROUP = 'per_group';

    public const QUALIFICATION_TOTAL = 'total';

    public function getQualifiers(
        Turnamen $turnamen,
        int $jumlahLolos = 2,
        string $mode = self::QUALIFICATION_PER_GROUP
    ): Collection {
        if ($mode === self::QUALIFICATION_TOTAL) {
            return $this->getQualifiersByTotal($turnamen, $jumlahLolos);
        }

        return $this->getQualifiersPerGroup($turnamen, $jumlahLolos);
    }

    public function describeTotalQualification(Turnamen $turnamen, int $totalSlots): array
    {
        $standings = $this->leaderboardService->getStandings($turnamen->id);
        $groupCount = $standings->count();
        $participantCount = $standings->sum(fn (array $grup) => $grup['standings']->count());

        if ($groupCount < 1) {
            throw new RuntimeException('Belum ada klasemen grup.');
        }

        if ($totalSlots < 2) {
            throw new RuntimeException('Jumlah lolos minimal 2.');
        }

        if ($totalSlots > $participantCount) {
            throw new RuntimeException('Jumlah lolos melebihi jumlah peserta di fase grup.');
        }

        if ($totalSlots < $groupCount) {
            throw new RuntimeException('Jumlah lolos minimal sama dengan jumlah grup (minimal juara 1 tiap grup).');
        }

        $base = intdiv($totalSlots, $groupCount);
        $extra = $totalSlots % $groupCount;
        $automaticCount = 0;

        foreach ($standings as $grup) {
            $automaticCount += min($base, $grup['standings']->count());
        }

        $luckyNeeded = max(0, $totalSlots - $automaticCount);

        return [
            'group_count' => $groupCount,
            'participant_count' => $participantCount,
            'total_slots' => $totalSlots,
            'base_per_group' => $base,
            'lucky_loser_slots' => $luckyNeeded,
            'automatic_count' => $automaticCount,
            'summary' => $luckyNeeded > 0
                ? sprintf(
                    '%d grup × top %d = %d, lalu %d terbaik dari sisa = %d lolos.',
                    $groupCount,
                    $base,
                    $automaticCount,
                    $luckyNeeded,
                    $totalSlots
                )
                : sprintf(
                    '%d grup × top %d = %d lolos.',
                    $groupCount,
                    $base,
                    $totalSlots
                ),
        ];
    }

    public function getQualifiersPerGroup(Turnamen $turnamen, int $jumlahLolos = 2): Collection
    {
        if ($jumlahLolos < 1) {
            throw new RuntimeException('Jumlah lolos minimal 1.');
        }

        $standings = $this->leaderboardService->getStandings($turnamen->id);

        $qualifiers = $standings->flatMap(function (array $grup) use ($jumlahLolos) {
            return $grup['standings']->take($jumlahLolos)->map(function (array $row) use ($grup) {
                return $this->mapStandingRowToQualifier($row, $grup, 'automatic');
            });
        })->values();

        return $this->seedQualifiers($qualifiers);
    }

    public function getQualifiersByTotal(Turnamen $turnamen, int $totalSlots): Collection
    {
        $plan = $this->describeTotalQualification($turnamen, $totalSlots);
        $standings = $this->leaderboardService->getStandings($turnamen->id);
        $base = $plan['base_per_group'];

        $automatic = collect();
        $remaining = collect();

        foreach ($standings as $grup) {
            $rows = $grup['standings']->values();
            $take = min($base, $rows->count());

            foreach ($rows->take($take) as $row) {
                $automatic->push($this->mapStandingRowToQualifier($row, $grup, 'automatic'));
            }

            foreach ($rows->slice($take) as $row) {
                $remaining->push($this->mapStandingRowToQualifier($row, $grup, 'lucky_loser'));
            }
        }

        $needed = max(0, $totalSlots - $automatic->count());

        $luckyLosers = $remaining
            ->sort(function (array $a, array $b) {
                if ($a['rank'] !== $b['rank']) {
                    return $a['rank'] <=> $b['rank'];
                }

                return GrupMember::comparePadelStandingRows($a, $b);
            })
            ->take($needed)
            ->values();

        $qualifiers = $automatic->concat($luckyLosers)->values();

        if ($qualifiers->count() < $totalSlots) {
            throw new RuntimeException('Peserta tidak cukup untuk mengisi kuota lolos knockout.');
        }

        return $this->seedQualifiers($qualifiers);
    }

    public function generateKnockoutBracket(
        Turnamen $turnamen,
        int $jumlahLolos = 2,
        string $mode = self::QUALIFICATION_PER_GROUP
    ): array {
        if (! $this->canEndGroupStage($turnamen)) {
            throw new RuntimeException('Fase grup belum selesai atau bracket knockout sudah dibuat.');
        }

        if (! in_array($mode, [self::QUALIFICATION_PER_GROUP, self::QUALIFICATION_TOTAL], true)) {
            throw new RuntimeException('Mode kualifikasi tidak valid.');
        }

        $qualifiers = $this->getQualifiers($turnamen, $jumlahLolos, $mode);

        if ($qualifiers->count() < 2) {
            throw new RuntimeException('Minimal 2 peserta lolos diperlukan untuk bracket knockout.');
        }

        return DB::transaction(function () use ($turnamen, $qualifiers, $mode, $jumlahLolos) {
            $result = $this->createSeededBracket($turnamen, $qualifiers);
            $result['qualification_mode'] = $mode;

            if ($mode === self::QUALIFICATION_TOTAL) {
                $plan = $this->describeTotalQualification($turnamen, $jumlahLolos);
                $result['jumlah_lolos_total'] = $jumlahLolos;
                $result['jumlah_lolos_per_grup'] = $plan['base_per_group'];
                $result['lucky_loser_slots'] = $plan['lucky_loser_slots'];
                $result['qualification_summary'] = $plan['summary'];
            } else {
                $result['jumlah_lolos_per_grup'] = $jumlahLolos;
                $result['jumlah_lolos_total'] = $qualifiers->count();
                $result['lucky_loser_slots'] = 0;
                $result['qualification_summary'] = null;
            }

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $grup
     * @return array<string, mixed>
     */
    protected function mapStandingRowToQualifier(array $row, array $grup, string $path): array
    {
        return [
            'id_pemain' => $row['id_pemain'],
            'id_peserta' => $row['id_peserta'] ?? null,
            'nama' => $row['nama'],
            'grup' => $grup['nama'],
            'rank' => $row['rank'],
            'poin_didapat' => $row['poin_didapat'],
            'set_menang' => $row['set_menang'],
            'games_menang' => $row['games_menang'],
            'stats_reached_at' => $row['stats_reached_at'] ?? null,
            'qualification_path' => $path,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $qualifiers
     * @return Collection<int, array<string, mixed>>
     */
    protected function seedQualifiers(Collection $qualifiers): Collection
    {
        return $qualifiers
            ->sort(function (array $a, array $b) {
                if ($a['rank'] !== $b['rank']) {
                    return $a['rank'] <=> $b['rank'];
                }

                return GrupMember::comparePadelStandingRows($a, $b);
            })
            ->values()
            ->map(function (array $qualifier, int $index) {
                $qualifier['seed'] = $index + 1;

                return $qualifier;
            });
    }
    protected function createSeededBracket(Turnamen $turnamen, Collection $qualifiers): array

    {

        $qualifierCount = $qualifiers->count();

        $bracketSize = $this->nextPowerOfTwo($qualifierCount);

        $rounds = $this->determineRounds($bracketSize);

        $roundMatches = $this->createEmptyRounds($turnamen, $rounds, $bracketSize);

        $this->linkRounds($roundMatches, $rounds);

        $this->createThirdPlaceMatch($turnamen, $roundMatches);

        $byeCount = $this->assignFirstRoundSeeding($roundMatches, $rounds, $qualifiers, $bracketSize);

        $this->optimizeFirstRoundByePairing($roundMatches, $rounds);



        return [

            'rounds' => $rounds,

            'matches_created' => collect($roundMatches)->flatten(1)->count(),

            'qualifiers' => $qualifierCount,

            'bracket_size' => $bracketSize,

            'bye_count' => $byeCount,

            'jumlah_lolos_per_grup' => null,

        ];

    }



    protected function nextPowerOfTwo(int $count): int

    {

        $size = 2;



        while ($size < $count) {

            $size *= 2;

        }



        return $size;

    }



    protected function determineRounds(int $bracketSize): array

    {

        if ($bracketSize <= 2) {

            return ['Final'];

        }



        if ($bracketSize <= 4) {

            return ['Semifinal', 'Final'];

        }



        if ($bracketSize <= 8) {

            return ['Perempatfinal', 'Semifinal', 'Final'];

        }



        return ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final'];

    }



    protected function createEmptyRounds(Turnamen $turnamen, array $rounds, int $bracketSize): array

    {

        $roundMatches = [];

        $matchCount = (int) ($bracketSize / 2);



        foreach ($rounds as $roundName) {

            $roundMatches[$roundName] = [];



            for ($i = 0; $i < $matchCount; $i++) {

                $roundMatches[$roundName][] = Pertandingan::create([

                    'id_turnamen' => $turnamen->id,

                    'id_grup' => null,

                    'nama_ronde' => $roundName,

                    'id_pemain1' => null,

                    'id_pemain2' => null,

                    'status' => 'scheduled',

                ]);

            }



            $matchCount = max(1, (int) ($matchCount / 2));

        }



        return $roundMatches;

    }



    /**
     * Rewire first-round feeders so each bye path is paired with a played match
     * in the next round whenever possible (avoids bye vs bye in round 2).
     */
    protected function optimizeFirstRoundByePairing(array $roundMatches, array $rounds): void
    {
        if (count($rounds) < 2) {
            return;
        }

        $firstRound = $rounds[0];
        $secondRound = $rounds[1];
        $firstMatches = $roundMatches[$firstRound];
        $secondMatches = $roundMatches[$secondRound];

        $byeMatches = [];
        $playedMatches = [];

        foreach ($firstMatches as $match) {
            if ($this->isByeMatch($match)) {
                $byeMatches[] = $match;
            } else {
                $playedMatches[] = $match;
            }
        }

        if ($byeMatches === [] || $playedMatches === []) {
            return;
        }

        foreach ($secondMatches as $match) {
            $match->update([
                'id_pemain1' => null,
                'id_pemain2' => null,
                'id_peserta1' => null,
                'id_peserta2' => null,
            ]);
        }

        foreach ($firstMatches as $match) {
            $match->update(['id_next_pertandingan' => null]);
        }

        $byeQueue = collect($byeMatches)->values();
        $playedQueue = collect($playedMatches)->values();
        $nextIndex = 0;

        while ($byeQueue->isNotEmpty() && $playedQueue->isNotEmpty() && $nextIndex < count($secondMatches)) {
            $next = $secondMatches[$nextIndex++];
            $this->linkFeedersToNextMatch($next, [$byeQueue->shift(), $playedQueue->shift()]);
        }

        while ($playedQueue->count() >= 2 && $nextIndex < count($secondMatches)) {
            $next = $secondMatches[$nextIndex++];
            $this->linkFeedersToNextMatch($next, [$playedQueue->shift(), $playedQueue->shift()]);
        }

        while ($byeQueue->count() >= 2 && $nextIndex < count($secondMatches)) {
            $next = $secondMatches[$nextIndex++];
            $this->linkFeedersToNextMatch($next, [$byeQueue->shift(), $byeQueue->shift()]);
        }

        $turnamen = Turnamen::find($firstMatches[0]->id_turnamen);

        if ($turnamen) {
            $this->resyncKnockoutBracketOccupants($turnamen);
        }
    }



    /**
     * @param  array<int, Pertandingan>  $feeders
     */
    protected function linkFeedersToNextMatch(Pertandingan $nextMatch, array $feeders): void
    {
        foreach ($feeders as $feeder) {
            $feeder->update(['id_next_pertandingan' => $nextMatch->id]);
        }
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



    /**
     * Create the third-place playoff and wire both semifinal matches so their
     * losers advance into it. Only relevant when a Semifinal round exists.
     */
    protected function createThirdPlaceMatch(Turnamen $turnamen, array $roundMatches): void
    {
        if (! isset($roundMatches['Semifinal'])) {
            return;
        }

        $semifinals = $roundMatches['Semifinal'];

        if (count($semifinals) < 2) {
            return;
        }

        $thirdPlace = Pertandingan::create([
            'id_turnamen' => $turnamen->id,
            'id_grup' => null,
            'nama_ronde' => 'Perebutan Juara 3',
            'id_pemain1' => null,
            'id_pemain2' => null,
            'status' => 'scheduled',
        ]);

        foreach ($semifinals as $semifinal) {
            $semifinal->update(['id_next_pertandingan_kalah' => $thirdPlace->id]);
        }
    }



    protected function assignFirstRoundSeeding(

        array $roundMatches,

        array $rounds,

        Collection $qualifiers,

        int $bracketSize

    ): int {

        $firstRound = $rounds[0];

        $matches = $roundMatches[$firstRound];

        $matchCount = count($matches);

        $byeCount = 0;



        for ($i = 0; $i < $matchCount; $i++) {

            $seed1 = $i + 1;

            $seed2 = $bracketSize - $i;

            $player1 = $qualifiers->firstWhere('seed', $seed1);

            $player2 = $qualifiers->firstWhere('seed', $seed2);

            $match = $matches[$i];



            if ($player1 && $player2) {

                $this->assignPlayersToMatch($match, $player1, $player2);

                continue;

            }



            if ($player1) {

                $this->completeByeMatch($match, $player1, 1, false);

                $byeCount++;

                continue;

            }



            if ($player2) {

                $this->completeByeMatch($match, $player2, 2, false);

                $byeCount++;

            }

        }



        return $byeCount;

    }



    protected function assignPlayersToMatch(Pertandingan $match, array $player1, array $player2): void

    {

        $match->update([

            'id_pemain1' => $player1['id_pemain'],

            'id_pemain2' => $player2['id_pemain'],

            'id_peserta1' => $player1['id_peserta'] ?? null,

            'id_peserta2' => $player2['id_peserta'] ?? null,

        ]);

    }



    protected function completeByeMatch(Pertandingan $match, array $winner, int $side, bool $advance = true): void

    {

        $payload = [

            'status' => 'completed',

            'id_pemenang' => $winner['id_pemain'],

            'id_peserta_pemenang' => $winner['id_peserta'] ?? null,

        ];



        if ($side === 1) {

            $payload['id_pemain1'] = $winner['id_pemain'];

            $payload['id_peserta1'] = $winner['id_peserta'] ?? null;

        } else {

            $payload['id_pemain2'] = $winner['id_pemain'];

            $payload['id_peserta2'] = $winner['id_peserta'] ?? null;

        }



        $match->update($payload);

        if ($advance) {
            $this->advanceWinner($match->fresh(), $winner['id_pemain'], $winner['id_peserta'] ?? null);
        }

    }



    /**
     * Return knockout matches grouped by round (as Eloquent models) in bracket
     * order, ready for score-input tables.
     *
     * @return \Illuminate\Support\Collection<int, array{nama_ronde: string, matches: \Illuminate\Support\Collection}>
     */
    public function getKnockoutRoundsWithMatches(Turnamen $turnamen): Collection
    {
        $roundOrder = ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final', 'Perebutan Juara 3'];

        $matchesByRound = Pertandingan::where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->with(array_merge([
                'peserta1.pemain1',
                'peserta2.pemain1',
                'pemain1',
                'pemain2',
                'skor',
                'pemenang',
                'pesertaPemenang.pemain1',
            ], TurnamenPeserta::partnerPemainEagerLoadsFor('peserta1'), TurnamenPeserta::partnerPemainEagerLoadsFor('peserta2'), TurnamenPeserta::partnerPemainEagerLoadsFor('pesertaPemenang')))
            ->orderBy('id')
            ->get()
            ->groupBy('nama_ronde');

        return collect($roundOrder)
            ->filter(fn ($round) => $matchesByRound->has($round))
            ->map(fn ($round) => [
                'nama_ronde' => $round,
                'matches' => $matchesByRound->get($round),
            ])
            ->values();
    }



    public function getBracketTree(Turnamen $turnamen): array

    {

        $this->resyncKnockoutBracketOccupants($turnamen);

        // The third-place playoff is not rendered as its own column; it is
        // appended into the Final column (below the final match) further down.
        $roundOrder = ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final'];

        $relations = array_merge(
            ['pemain1', 'pemain2', 'peserta1.pemain1', 'peserta2.pemain1', 'pemenang', 'pesertaPemenang.pemain1', 'skor'],
            TurnamenPeserta::partnerPemainEagerLoadsFor('peserta1'),
            TurnamenPeserta::partnerPemainEagerLoadsFor('peserta2'),
            TurnamenPeserta::partnerPemainEagerLoadsFor('pesertaPemenang')
        );

        $allMatches = Pertandingan::where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->with($relations)
            ->orderBy('id')
            ->get();

        $allMatchesById = $allMatches->keyBy('id');
        $matchesByRound = $allMatches->groupBy('nama_ronde');

        $result = [];
        $previousOrdered = null;

        foreach ($roundOrder as $round) {

            $matches = $matchesByRound->get($round, collect());

            if ($matches->isEmpty()) {

                continue;

            }

            $ordered = $this->orderMatchesForBracketDisplay($matches, $previousOrdered, $allMatchesById);
            $previousOrdered = $ordered;

            $result[] = [

                'nama_ronde' => $round,

                'matches' => $ordered->map(function (Pertandingan $m) {

                    return $this->formatMatchForBracket($m);

                })->values()->all(),

            ];

        }



        $thirdPlaceMatches = $matchesByRound->get('Perebutan Juara 3', collect());



        if ($thirdPlaceMatches->isNotEmpty()) {

            $formatted = $thirdPlaceMatches->map(function (Pertandingan $m) {

                return $this->formatMatchForBracket($m);

            })->values()->all();



            foreach ($result as $index => $round) {

                if ($round['nama_ronde'] === 'Final') {

                    $result[$index]['matches'] = array_merge($round['matches'], $formatted);

                    break;

                }

            }

        }



        return $result;

    }



    /**
     * Order matches for bracket display so feeders that share the same next
     * match appear adjacent (bye beside its paired played match).
     */
    protected function orderMatchesForBracketDisplay(
        Collection $matches,
        ?Collection $previousRoundOrdered,
        Collection $allMatchesById
    ): Collection {
        if ($matches->isEmpty()) {
            return $matches;
        }

        if ($previousRoundOrdered === null) {
            return $this->orderFirstRoundMatchesForDisplay($matches);
        }

        return $this->orderFeederAlignedMatches($matches, $previousRoundOrdered, $allMatchesById);
    }



    protected function orderFirstRoundMatchesForDisplay(Collection $matches): Collection
    {
        return $matches
            ->groupBy('id_next_pertandingan')
            ->sortBy(fn (Collection $group, $nextId) => $nextId ? (int) $nextId : PHP_INT_MAX)
            ->flatMap(function (Collection $group) {
                $played = $group
                    ->reject(fn (Pertandingan $match) => $this->isByeMatch($match))
                    ->sortBy('id')
                    ->values();

                $byes = $group
                    ->filter(fn (Pertandingan $match) => $this->isByeMatch($match))
                    ->sortBy('id')
                    ->values();

                return $played->concat($byes);
            })
            ->values();
    }



    protected function orderFeederAlignedMatches(
        Collection $matches,
        Collection $previousRoundOrdered,
        Collection $allMatchesById
    ): Collection {
        $indexById = $previousRoundOrdered->values()->mapWithKeys(
            fn (Pertandingan $match, int $index) => [$match->id => $index]
        );

        $feedersByTarget = $allMatchesById
            ->filter(fn (Pertandingan $match) => $match->id_next_pertandingan)
            ->groupBy('id_next_pertandingan');

        return $matches
            ->sortBy(function (Pertandingan $match) use ($indexById, $feedersByTarget) {
                $feeders = $feedersByTarget->get($match->id, collect());

                if ($feeders->isEmpty()) {
                    return PHP_INT_MAX;
                }

                $positions = $feeders->map(fn (Pertandingan $feeder) => $indexById[$feeder->id] ?? PHP_INT_MAX);

                return ($positions->min() + $positions->max()) / 2;
            })
            ->values();
    }



    public function formatMatchForBracket(Pertandingan $m): array

    {

        $skorSummary = $m->skor->map(function ($s) {

            return $s->skor_pemain1 . '-' . $s->skor_pemain2;

        })->implode(', ');



        $isBye = $m->status === 'completed'

            && (($m->id_pemain1 && ! $m->id_pemain2) || (! $m->id_pemain1 && $m->id_pemain2));



        $side1Players = $this->formatSidePlayers($m, 1);
        $side2Players = $this->formatSidePlayers($m, 2);
        $winnerSide = $this->resolveWinnerSide($m);
        $loserSide = $winnerSide === 1 ? 2 : ($winnerSide === 2 ? 1 : null);

        return [

            'id' => $m->id,

            'pemain1' => $m->side1_label,

            'pemain2' => $m->side2_label,

            'pemain1_id' => $m->id_pemain1,

            'pemain2_id' => $m->id_pemain2,

            'pemain1_ids' => $this->resolveSidePemainIds($m, 1),

            'pemain2_ids' => $this->resolveSidePemainIds($m, 2),

            'pemain1_players' => $side1Players,

            'pemain2_players' => $side2Players,

            'peserta1_id' => $m->id_peserta1,

            'peserta2_id' => $m->id_peserta2,

            'pemenang' => $m->winner_label,

            'pemenang_id' => $m->id_pemenang,

            'peserta_pemenang_id' => $m->id_peserta_pemenang,

            'pemenang_players' => $winnerSide === 1 ? $side1Players : ($winnerSide === 2 ? $side2Players : []),

            'runner_up' => $loserSide === 1 ? $m->side1_label : ($loserSide === 2 ? $m->side2_label : null),

            'runner_up_players' => $loserSide === 1 ? $side1Players : ($loserSide === 2 ? $side2Players : []),

            'status' => $m->status,

            'is_bye' => $isBye,

            'is_third_place' => $m->nama_ronde === 'Perebutan Juara 3',

            'nama_ronde' => $m->nama_ronde,

            'skor' => $skorSummary,

            'id_next_pertandingan' => $m->id_next_pertandingan,

            'id_next_pertandingan_kalah' => $m->id_next_pertandingan_kalah,

        ];

    }

    /**
     * @return array<int, array{id: int, nama: string, foto_url: string}>
     */
    protected function formatSidePlayers(Pertandingan $match, int $side): array
    {
        $ids = $this->resolveSidePemainIds($match, $side);

        if ($ids === []) {
            return [];
        }

        $photoService = app(PemainPhotoService::class);
        $playersById = Pemain::whereIn('id', $ids)->get()->keyBy('id');

        $formatted = [];

        foreach ($ids as $id) {
            $pemain = $playersById->get($id);

            if (! $pemain) {
                continue;
            }

            $formatted[] = [
                'id' => (int) $pemain->id,
                'nama' => $pemain->nama,
                'foto_url' => $photoService->url($pemain->foto),
            ];
        }

        return $formatted;
    }

    protected function resolveWinnerSide(Pertandingan $match): ?int
    {
        if (! $match->id_pemenang && ! $match->id_peserta_pemenang) {
            return null;
        }

        if ($match->id_peserta_pemenang) {
            if ((int) $match->id_peserta1 === (int) $match->id_peserta_pemenang) {
                return 1;
            }

            if ((int) $match->id_peserta2 === (int) $match->id_peserta_pemenang) {
                return 2;
            }
        }

        if ($match->id_pemenang) {
            $side1Ids = $this->resolveSidePemainIds($match, 1);
            $side2Ids = $this->resolveSidePemainIds($match, 2);
            $winnerId = (int) $match->id_pemenang;

            if (in_array($winnerId, $side1Ids, true)) {
                return 1;
            }

            if (in_array($winnerId, $side2Ids, true)) {
                return 2;
            }
        }

        return null;
    }



    public function advanceWinner(Pertandingan $pertandingan, int $winnerId, ?int $winnerPesertaId = null): void

    {

        if ($pertandingan->id_grup || ! $pertandingan->id_next_pertandingan) {

            return;

        }



        $next = Pertandingan::find($pertandingan->id_next_pertandingan);



        if (! $next) {

            return;

        }



        if (! $winnerPesertaId) {
            $winnerPesertaId = $pertandingan->resolvePesertaIdForPemain($winnerId);
        }

        if ($winnerPesertaId) {
            $peserta = TurnamenPeserta::find($winnerPesertaId);

            if ($peserta) {
                $winnerId = (int) $peserta->id_pemain1;
            }
        }



        $feeders = Pertandingan::where('id_next_pertandingan', $next->id)

            ->orderBy('id')

            ->pluck('id')

            ->values();



        $slot = $feeders->search($pertandingan->id);



        if ($slot === 0) {

            $next->update([

                'id_pemain1' => $winnerId,

                'id_peserta1' => $winnerPesertaId,

            ]);

        } elseif ($slot === 1) {

            $next->update([

                'id_pemain2' => $winnerId,

                'id_peserta2' => $winnerPesertaId,

            ]);

        }

    }



    /**
     * Rebuild knockout round occupants from completed feeder matches only.
     * Fixes stale slots left by bye advances before feeder links were rewired.
     */
    public function resyncKnockoutBracketOccupants(Turnamen $turnamen): void
    {
        $roundOrder = ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final'];

        $existingRounds = Pertandingan::query()
            ->where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->whereIn('nama_ronde', $roundOrder)
            ->pluck('nama_ronde')
            ->unique();

        $activeRounds = array_values(array_filter(
            $roundOrder,
            fn (string $round) => $existingRounds->contains($round)
        ));

        for ($roundIndex = 1; $roundIndex < count($activeRounds); $roundIndex++) {
            $roundName = $activeRounds[$roundIndex];

            $matches = Pertandingan::query()
                ->where('id_turnamen', $turnamen->id)
                ->whereNull('id_grup')
                ->where('nama_ronde', $roundName)
                ->orderBy('id')
                ->get();

            foreach ($matches as $match) {
                $match->update([
                    'id_pemain1' => null,
                    'id_pemain2' => null,
                    'id_peserta1' => null,
                    'id_peserta2' => null,
                ]);
            }

            foreach ($matches as $nextMatch) {
                $feeders = Pertandingan::query()
                    ->where('id_next_pertandingan', $nextMatch->id)
                    ->orderBy('id')
                    ->get();

                foreach ($feeders as $feeder) {
                    if ($feeder->status !== 'completed' || (! $feeder->id_pemenang && ! $feeder->id_peserta_pemenang)) {
                        continue;
                    }

                    $this->advanceWinner(
                        $feeder,
                        (int) $feeder->id_pemenang,
                        $feeder->id_peserta_pemenang ? (int) $feeder->id_peserta_pemenang : null
                    );
                }
            }
        }
    }

    /**
     * Advance the losing side of a knockout match into the third-place playoff.
     * Uses the dedicated loser link so only semifinal losers are propagated.
     */
    public function advanceLoser(Pertandingan $pertandingan, int $loserId, ?int $loserPesertaId = null): void
    {
        if ($pertandingan->id_grup || ! $pertandingan->id_next_pertandingan_kalah) {
            return;
        }

        $next = Pertandingan::find($pertandingan->id_next_pertandingan_kalah);

        if (! $next) {
            return;
        }

        $loserPesertaId = $loserPesertaId ?? $pertandingan->resolvePesertaIdForPemain($loserId);

        $feeders = Pertandingan::where('id_next_pertandingan_kalah', $next->id)
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $slot = $feeders->search($pertandingan->id);

        if ($slot === 0) {
            $next->update([
                'id_pemain1' => $loserId,
                'id_peserta1' => $loserPesertaId,
            ]);
        } elseif ($slot === 1) {
            $next->update([
                'id_pemain2' => $loserId,
                'id_peserta2' => $loserPesertaId,
            ]);
        }
    }

    /**
     * A knockout match is editable when completed with scores and every
     * immediate downstream match is still unplayed.
     */
    public function canEditKnockoutScore(Pertandingan $pertandingan): bool
    {
        if ($pertandingan->id_grup || $pertandingan->status !== 'completed') {
            return false;
        }

        if (! $pertandingan->relationLoaded('skor')) {
            $pertandingan->load('skor');
        }

        if ($pertandingan->skor->isEmpty()) {
            return false;
        }

        $turnamen = $pertandingan->relationLoaded('turnamen')
            ? $pertandingan->turnamen
            : Turnamen::find($pertandingan->id_turnamen);

        if (! $turnamen || $turnamen->isMahjong() || $turnamen->status === 'completed') {
            return false;
        }

        return ! $this->hasPlayedDownstreamMatch($pertandingan);
    }

    public function hasPlayedDownstreamMatch(Pertandingan $pertandingan): bool
    {
        foreach ([$pertandingan->id_next_pertandingan, $pertandingan->id_next_pertandingan_kalah] as $nextId) {
            if (! $nextId) {
                continue;
            }

            $next = Pertandingan::query()
                ->withCount('skor')
                ->find($nextId);

            if (! $next) {
                continue;
            }

            if ($next->status === 'completed' || (int) $next->skor_count > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear this feeder's occupant slots in immediate next matches.
     */
    public function clearAdvancementFrom(Pertandingan $pertandingan): void
    {
        if ($pertandingan->id_grup) {
            return;
        }

        if ($pertandingan->id_next_pertandingan) {
            $this->clearFeederSlot(
                (int) $pertandingan->id_next_pertandingan,
                (int) $pertandingan->id,
                'id_next_pertandingan'
            );
        }

        if ($pertandingan->id_next_pertandingan_kalah) {
            $this->clearFeederSlot(
                (int) $pertandingan->id_next_pertandingan_kalah,
                (int) $pertandingan->id,
                'id_next_pertandingan_kalah'
            );
        }
    }

    protected function clearFeederSlot(int $nextMatchId, int $feederMatchId, string $feederColumn): void
    {
        $next = Pertandingan::find($nextMatchId);

        if (! $next) {
            return;
        }

        if ($next->status === 'completed' || $next->skor()->exists()) {
            throw new RuntimeException('Pertandingan berikutnya sudah dimainkan, skor tidak dapat diubah.');
        }

        $feeders = Pertandingan::query()
            ->where($feederColumn, $next->id)
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $slot = $feeders->search($feederMatchId);

        if ($slot === 0) {
            $next->update([
                'id_pemain1' => null,
                'id_peserta1' => null,
            ]);
        } elseif ($slot === 1) {
            $next->update([
                'id_pemain2' => null,
                'id_peserta2' => null,
            ]);
        }
    }

    protected function resolveSidePemainIds(Pertandingan $match, int $side): array
    {
        $pesertaId = $side === 1 ? $match->id_peserta1 : $match->id_peserta2;
        $pemainId = $side === 1 ? $match->id_pemain1 : $match->id_pemain2;

        if ($pesertaId) {
            $peserta = $side === 1 ? $match->peserta1 : $match->peserta2;

            if (! $peserta) {
                $peserta = \App\Models\TurnamenPeserta::find($pesertaId);
            }

            if ($peserta) {
                return $peserta->pemainIds();
            }
        }

        return $pemainId ? [(int) $pemainId] : [];
    }

    /**
     * Swap two participants between (or within) first-round knockout matches.
     *
     * Only slots on directly-seeded matches (no feeder matches, not yet played)
     * can be swapped, so downstream matches never desync. Bye participants can
     * also be swapped; their auto-advanced winner is recomputed afterwards.
     *
     * @return array{bracket: array<int, mixed>}
     */
    public function swapParticipants(
        Turnamen $turnamen,
        int $sourceMatchId,
        int $sourceSlot,
        int $targetMatchId,
        int $targetSlot
    ): array {
        if (! in_array($sourceSlot, [1, 2], true) || ! in_array($targetSlot, [1, 2], true)) {
            throw new RuntimeException('Slot peserta tidak valid.');
        }

        if ($sourceMatchId === $targetMatchId && $sourceSlot === $targetSlot) {
            throw new RuntimeException('Pilih peserta yang berbeda untuk ditukar.');
        }

        return DB::transaction(function () use ($turnamen, $sourceMatchId, $sourceSlot, $targetMatchId, $targetSlot) {
            $sourceMatch = $this->findSwappableMatch($turnamen, $sourceMatchId);
            $targetMatch = $sourceMatchId === $targetMatchId
                ? $sourceMatch
                : $this->findSwappableMatch($turnamen, $targetMatchId);

            $sourceOccupant = $this->readBracketSlot($sourceMatch, $sourceSlot);
            $targetOccupant = $this->readBracketSlot($targetMatch, $targetSlot);

            if (! $sourceOccupant['id_pemain'] || ! $targetOccupant['id_pemain']) {
                throw new RuntimeException('Kedua slot yang dipilih harus berisi peserta.');
            }

            // Byes have already advanced a winner; block the swap if the next
            // match has started so we never overwrite a played result.
            $this->assertByeAdvancementEditable($sourceMatch);
            $this->assertByeAdvancementEditable($targetMatch);

            if ($sourceMatch->is($targetMatch)) {
                $payload = [];
                $this->fillBracketSlot($payload, $sourceSlot, $targetOccupant);
                $this->fillBracketSlot($payload, $targetSlot, $sourceOccupant);
                $sourceMatch->update($payload);
            } else {
                $sourcePayload = [];
                $this->fillBracketSlot($sourcePayload, $sourceSlot, $targetOccupant);
                $sourceMatch->update($sourcePayload);

                $targetPayload = [];
                $this->fillBracketSlot($targetPayload, $targetSlot, $sourceOccupant);
                $targetMatch->update($targetPayload);
            }

            $this->reconcileByeMatch($sourceMatch->fresh());

            if (! $sourceMatch->is($targetMatch)) {
                $this->reconcileByeMatch($targetMatch->fresh());
            }

            return [
                'bracket' => $this->getBracketTree($turnamen),
            ];
        });
    }

    protected function findSwappableMatch(Turnamen $turnamen, int $matchId): Pertandingan
    {
        $match = Pertandingan::where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->where('id', $matchId)
            ->first();

        if (! $match) {
            throw new RuntimeException('Pertandingan bracket tidak ditemukan.');
        }

        // A recorded score means the match was actually played; byes have none.
        if ($match->skor()->exists()) {
            throw new RuntimeException('Pertandingan yang sudah dimainkan tidak dapat diubah.');
        }

        if ($match->feederMatches()->exists()) {
            throw new RuntimeException('Hanya peserta pada babak pertama yang dapat ditukar.');
        }

        return $match;
    }

    protected function isByeMatch(Pertandingan $match): bool
    {
        $hasSlot1 = ! is_null($match->id_pemain1);
        $hasSlot2 = ! is_null($match->id_pemain2);

        return $hasSlot1 xor $hasSlot2;
    }

    protected function assertByeAdvancementEditable(Pertandingan $match): void
    {
        if (! $this->isByeMatch($match) || ! $match->id_next_pertandingan) {
            return;
        }

        $next = Pertandingan::find($match->id_next_pertandingan);

        if (! $next) {
            return;
        }

        if ($next->status === 'completed' || $next->skor()->exists()) {
            throw new RuntimeException('Peserta bye tidak dapat ditukar karena pertandingan babak berikutnya sudah dimulai.');
        }
    }

    protected function reconcileByeMatch(Pertandingan $match): void
    {
        if (! $this->isByeMatch($match)) {
            return;
        }

        $winnerOnSlot1 = ! is_null($match->id_pemain1);
        $winnerPemain = $winnerOnSlot1 ? $match->id_pemain1 : $match->id_pemain2;
        $winnerPeserta = $winnerOnSlot1 ? $match->id_peserta1 : $match->id_peserta2;

        $match->update([
            'status' => 'completed',
            'id_pemenang' => $winnerPemain,
            'id_peserta_pemenang' => $winnerPeserta,
        ]);

        $this->advanceWinner(
            $match->fresh(),
            (int) $winnerPemain,
            $winnerPeserta ? (int) $winnerPeserta : null
        );
    }

    protected function readBracketSlot(Pertandingan $match, int $slot): array
    {
        if ($slot === 1) {
            return [
                'id_pemain' => $match->id_pemain1,
                'id_peserta' => $match->id_peserta1,
            ];
        }

        return [
            'id_pemain' => $match->id_pemain2,
            'id_peserta' => $match->id_peserta2,
        ];
    }

    protected function fillBracketSlot(array &$payload, int $slot, array $occupant): void
    {
        if ($slot === 1) {
            $payload['id_pemain1'] = $occupant['id_pemain'];
            $payload['id_peserta1'] = $occupant['id_peserta'];

            return;
        }

        $payload['id_pemain2'] = $occupant['id_pemain'];
        $payload['id_peserta2'] = $occupant['id_peserta'];
    }

}
