<?php



namespace App\Services;



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

            ->whereIn('nama_ronde', ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final'])

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



    public function getQualifiers(Turnamen $turnamen, int $jumlahLolos = 2): Collection

    {

        if ($jumlahLolos < 1) {

            throw new RuntimeException('Jumlah lolos minimal 1.');

        }



        $standings = $this->leaderboardService->getStandings($turnamen->id);



        $qualifiers = $standings->flatMap(function (array $grup) use ($jumlahLolos) {

            return $grup['standings']->take($jumlahLolos)->map(function (array $row) use ($grup) {

                return [

                    'id_pemain' => $row['id_pemain'],

                    'id_peserta' => $row['id_peserta'] ?? null,

                    'nama' => $row['nama'],

                    'grup' => $grup['nama'],

                    'rank' => $row['rank'],

                    'poin_didapat' => $row['poin_didapat'],

                    'set_menang' => $row['set_menang'],

                    'games_menang' => $row['games_menang'],

                ];

            });

        })->values();



        return $qualifiers

            ->sortBy([

                ['rank', 'asc'],

                ['poin_didapat', 'desc'],

                ['set_menang', 'desc'],

                ['games_menang', 'desc'],

            ])

            ->values()

            ->map(function (array $qualifier, int $index) {

                $qualifier['seed'] = $index + 1;



                return $qualifier;

            });

    }



    public function generateKnockoutBracket(Turnamen $turnamen, int $jumlahLolos = 2): array

    {

        if (! $this->canEndGroupStage($turnamen)) {

            throw new RuntimeException('Fase grup belum selesai atau bracket knockout sudah dibuat.');

        }



        $qualifiers = $this->getQualifiers($turnamen, $jumlahLolos);



        if ($qualifiers->count() < 2) {

            throw new RuntimeException('Minimal 2 peserta lolos diperlukan untuk bracket knockout.');

        }



        return DB::transaction(function () use ($turnamen, $qualifiers) {

            return $this->createSeededBracket($turnamen, $qualifiers);

        });

    }



    protected function createSeededBracket(Turnamen $turnamen, Collection $qualifiers): array

    {

        $qualifierCount = $qualifiers->count();

        $bracketSize = $this->nextPowerOfTwo($qualifierCount);

        $rounds = $this->determineRounds($bracketSize);

        $roundMatches = $this->createEmptyRounds($turnamen, $rounds, $bracketSize);



        $this->linkRounds($roundMatches, $rounds);

        $byeCount = $this->assignFirstRoundSeeding($roundMatches, $rounds, $qualifiers, $bracketSize);



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

                $this->completeByeMatch($match, $player1, 1);

                $byeCount++;

                continue;

            }



            if ($player2) {

                $this->completeByeMatch($match, $player2, 2);

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



    protected function completeByeMatch(Pertandingan $match, array $winner, int $side): void

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

        $this->advanceWinner($match->fresh(), $winner['id_pemain'], $winner['id_peserta'] ?? null);

    }



    public function getBracketTree(Turnamen $turnamen): array

    {

        $roundOrder = ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final'];

        $result = [];



        foreach ($roundOrder as $round) {

            $matches = Pertandingan::where('id_turnamen', $turnamen->id)

                ->whereNull('id_grup')

                ->where('nama_ronde', $round)

                ->with(['pemain1', 'pemain2', 'peserta1.pemain1', 'peserta1.pemain2', 'peserta2.pemain1', 'peserta2.pemain2', 'pemenang', 'pesertaPemenang.pemain1', 'pesertaPemenang.pemain2', 'skor'])

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



        $isBye = $m->status === 'completed'

            && (($m->id_pemain1 && ! $m->id_pemain2) || (! $m->id_pemain1 && $m->id_pemain2));



        return [

            'id' => $m->id,

            'pemain1' => $m->side1_label,

            'pemain2' => $m->side2_label,

            'pemain1_id' => $m->id_pemain1,

            'pemain2_id' => $m->id_pemain2,

            'pemain1_ids' => $this->resolveSidePemainIds($m, 1),

            'pemain2_ids' => $this->resolveSidePemainIds($m, 2),

            'peserta1_id' => $m->id_peserta1,

            'peserta2_id' => $m->id_peserta2,

            'pemenang' => $m->winner_label,

            'pemenang_id' => $m->id_pemenang,

            'peserta_pemenang_id' => $m->id_peserta_pemenang,

            'status' => $m->status,

            'is_bye' => $isBye,

            'skor' => $skorSummary,

            'id_next_pertandingan' => $m->id_next_pertandingan,

        ];

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



        $winnerPesertaId = $winnerPesertaId ?? $pertandingan->resolvePesertaIdForPemain($winnerId);



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

}
