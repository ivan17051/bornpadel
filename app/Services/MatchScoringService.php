<?php

namespace App\Services;

use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\PertandinganSkor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MatchScoringService
{
    protected $knockoutBracketService;
    protected $pointRewardService;

    public function __construct(
        KnockoutBracketService $knockoutBracketService,
        PointRewardService $pointRewardService
    ) {
        $this->knockoutBracketService = $knockoutBracketService;
        $this->pointRewardService = $pointRewardService;
    }

    public function recordScore(Pertandingan $pertandingan, array $sets): Pertandingan
    {
        if ($pertandingan->status === 'completed') {
            throw new RuntimeException('Pertandingan ini sudah selesai dan tidak dapat diubah.');
        }

        if (! $pertandingan->id_pemain1 || ! $pertandingan->id_pemain2) {
            throw new RuntimeException('Kedua peserta harus sudah ditentukan sebelum input skor.');
        }

        $result = $this->calculateMatchResult($sets, $pertandingan->id_pemain1, $pertandingan->id_pemain2);

        return DB::transaction(function () use ($pertandingan, $sets, $result) {
            $pertandingan->skor()->delete();

            foreach ($sets as $index => $set) {
                PertandinganSkor::create([
                    'id_pertandingan' => $pertandingan->id,
                    'set_ke' => $index + 1,
                    'skor_pemain1' => $set['skor_pemain1'],
                    'skor_pemain2' => $set['skor_pemain2'],
                ]);
            }

            $winnerPesertaId = $pertandingan->resolvePesertaIdForPemain($result['winner_id']);

            $pertandingan->update([
                'id_pemenang' => $result['winner_id'],
                'id_peserta_pemenang' => $winnerPesertaId,
                'status' => 'completed',
            ]);

            if ($pertandingan->id_grup) {
                $this->updateGrupMemberStats(
                    $pertandingan->id_grup,
                    $result['winner_id'],
                    $result['loser_id'],
                    $winnerPesertaId,
                    $pertandingan->resolvePesertaIdForPemain($result['loser_id']),
                    $result['winner_sets'],
                    $result['loser_sets'],
                    $result['winner_games'],
                    $result['loser_games']
                );
            } else {
                $this->knockoutBracketService->advanceWinner(
                    $pertandingan,
                    $result['winner_id'],
                    $winnerPesertaId
                );
            }

            $this->pointRewardService->awardMatchWin($pertandingan->fresh());

            return $pertandingan->fresh(['skor', 'pemain1', 'pemain2', 'peserta1', 'peserta2', 'pemenang', 'pesertaPemenang', 'grup']);
        });
    }

    public function calculateMatchResult(array $sets, int $pemain1Id, int $pemain2Id): array
    {
        $setsWonP1 = 0;
        $setsWonP2 = 0;
        $gamesP1 = 0;
        $gamesP2 = 0;

        foreach ($sets as $set) {
            $s1 = (int) $set['skor_pemain1'];
            $s2 = (int) $set['skor_pemain2'];

            if ($s1 === $s2) {
                throw new RuntimeException('Set tidak boleh seri. Setiap set harus memiliki pemenang.');
            }

            $gamesP1 += $s1;
            $gamesP2 += $s2;

            if ($s1 > $s2) {
                $setsWonP1++;
            } else {
                $setsWonP2++;
            }
        }

        $setsToWin = 3;

        if ($setsWonP1 < $setsToWin && $setsWonP2 < $setsToWin) {
            throw new RuntimeException('Pemenang harus memenangkan minimal 3 set (Best of 5).');
        }

        if ($setsWonP1 >= $setsToWin && $setsWonP2 >= $setsToWin) {
            throw new RuntimeException('Skor tidak valid. Hanya satu pihak yang boleh memenangkan 3 set.');
        }

        $p1Won = $setsWonP1 >= $setsToWin;

        return [
            'winner_id' => $p1Won ? $pemain1Id : $pemain2Id,
            'loser_id' => $p1Won ? $pemain2Id : $pemain1Id,
            'winner_sets' => $p1Won ? $setsWonP1 : $setsWonP2,
            'loser_sets' => $p1Won ? $setsWonP2 : $setsWonP1,
            'winner_games' => $p1Won ? $gamesP1 : $gamesP2,
            'loser_games' => $p1Won ? $gamesP2 : $gamesP1,
            'sets_won_pemain1' => $setsWonP1,
            'sets_won_pemain2' => $setsWonP2,
        ];
    }

    protected function updateGrupMemberStats(
        int $grupId,
        int $winnerId,
        int $loserId,
        ?int $winnerPesertaId,
        ?int $loserPesertaId,
        int $winnerSets,
        int $loserSets,
        int $winnerGames,
        int $loserGames
    ): void {
        $winnerMember = $this->findGrupMember($grupId, $winnerId, $winnerPesertaId);
        $loserMember = $this->findGrupMember($grupId, $loserId, $loserPesertaId);

        if (! $winnerMember || ! $loserMember) {
            throw new RuntimeException('Kedua peserta harus terdaftar di grup untuk memperbarui klasemen.');
        }

        $winnerMember->increment('poin_didapat', 2);
        $winnerMember->increment('set_menang', $winnerSets);
        $winnerMember->increment('games_menang', $winnerGames);

        $loserMember->increment('set_menang', $loserSets);
        $loserMember->increment('games_menang', $loserGames);
    }

    protected function findGrupMember(int $grupId, int $pemainId, ?int $pesertaId): ?GrupMember
    {
        $query = GrupMember::where('id_grup', $grupId);

        if ($pesertaId) {
            return $query->where('id_turnamen_peserta', $pesertaId)->first();
        }

        return $query->where('id_pemain', $pemainId)->first();
    }
}
