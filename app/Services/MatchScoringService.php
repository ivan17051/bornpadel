<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\PertandinganSkor;
use App\Models\Turnamen;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MatchScoringService
{
    public const MIN_SETS = 1;
    public const MAX_SETS = 7;

    protected $knockoutBracketService;
    protected $pointRewardService;

    public function __construct(
        KnockoutBracketService $knockoutBracketService,
        PointRewardService $pointRewardService
    ) {
        $this->knockoutBracketService = $knockoutBracketService;
        $this->pointRewardService = $pointRewardService;
    }

    public function canEditGroupScore(Pertandingan $pertandingan): bool
    {
        if ($pertandingan->status !== 'completed') {
            return false;
        }

        $turnamen = $pertandingan->relationLoaded('turnamen')
            ? $pertandingan->turnamen
            : Turnamen::find($pertandingan->id_turnamen);

        if (! $turnamen || $turnamen->isMahjong() || $turnamen->status === 'completed') {
            return false;
        }

        if ($pertandingan->isFriendlyMatch() || $turnamen->isFriendly()) {
            return true;
        }

        if (! $pertandingan->id_grup) {
            return false;
        }

        return ! $this->knockoutBracketService->hasKnockoutBracket($turnamen);
    }

    public function canEditKnockoutScore(Pertandingan $pertandingan): bool
    {
        if ($pertandingan->status === 'cancelled') {
            return false;
        }

        return $this->knockoutBracketService->canEditKnockoutScore($pertandingan);
    }

    public function canEditScore(Pertandingan $pertandingan): bool
    {
        if ($pertandingan->status === 'cancelled') {
            return false;
        }

        return $this->canEditGroupScore($pertandingan)
            || $this->canEditKnockoutScore($pertandingan);
    }

    public function recordScore(Pertandingan $pertandingan, array $sets): Pertandingan
    {
        if ($pertandingan->status === 'cancelled') {
            throw new RuntimeException('Pertandingan ini sudah dibatalkan dan tidak dapat diisi skor.');
        }

        if ($pertandingan->status === 'completed') {
            if ($pertandingan->isFriendlyMatch()) {
                return $this->updateCompletedFriendlyScore($pertandingan, $sets);
            }

            if ($pertandingan->id_grup) {
                return $this->updateCompletedGroupScore($pertandingan, $sets);
            }

            return $this->updateCompletedKnockoutScore($pertandingan, $sets);
        }

        if (! $pertandingan->id_pemain1 || ! $pertandingan->id_pemain2) {
            throw new RuntimeException('Kedua peserta harus sudah ditentukan sebelum input skor.');
        }

        $result = $this->calculateMatchResult($sets, $pertandingan->id_pemain1, $pertandingan->id_pemain2);

        return DB::transaction(function () use ($pertandingan, $sets, $result) {
            $this->replaceMatchSets($pertandingan, $sets);

            $winnerPesertaId = $pertandingan->resolvePesertaIdForPemain($result['winner_id']);
            $loserPesertaId = $pertandingan->resolvePesertaIdForPemain($result['loser_id']);

            $pertandingan->update([
                'id_pemenang' => $result['winner_id'],
                'id_peserta_pemenang' => $winnerPesertaId,
                'status' => 'completed',
            ]);

            $pertandingan = $pertandingan->fresh();

            if ($pertandingan->isFriendlyMatch()) {
                $this->applyFriendlyGroupStats(
                    $pertandingan,
                    $result['winner_sets'],
                    $result['loser_sets'],
                    $result['winner_games'],
                    $result['loser_games']
                );
            } elseif ($pertandingan->id_grup) {
                $this->applyGrupMemberStats(
                    $pertandingan->id_grup,
                    $result['winner_id'],
                    $result['loser_id'],
                    $winnerPesertaId,
                    $loserPesertaId,
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

                $this->knockoutBracketService->advanceLoser(
                    $pertandingan,
                    $result['loser_id'],
                    $loserPesertaId
                );
            }

            // Friendly never awards lifetime total_poin.
            if (! $pertandingan->isFriendlyMatch()) {
                $this->pointRewardService->awardMatchWin($pertandingan->fresh());
            }

            return $pertandingan->fresh([
                'skor', 'pemain1', 'pemain2', 'pemain1Partner', 'pemain2Partner',
                'peserta1', 'peserta2', 'pemenang', 'pesertaPemenang', 'grup', 'grup1', 'grup2',
            ]);
        });
    }

    protected function updateCompletedGroupScore(Pertandingan $pertandingan, array $sets): Pertandingan
    {
        if (! $this->canEditGroupScore($pertandingan)) {
            throw new RuntimeException('Skor pertandingan ini tidak dapat diubah. Bracket knockout sudah dibuat atau pertandingan bukan fase grup.');
        }

        return $this->rewriteCompletedMatchScore($pertandingan, $sets, true);
    }

    protected function updateCompletedFriendlyScore(Pertandingan $pertandingan, array $sets): Pertandingan
    {
        if (! $this->canEditGroupScore($pertandingan)) {
            throw new RuntimeException('Skor Friendly tidak dapat diubah.');
        }

        if (! $pertandingan->id_pemain1 || ! $pertandingan->id_pemain2) {
            throw new RuntimeException('Kedua peserta harus sudah ditentukan sebelum mengubah skor.');
        }

        $pertandingan->loadMissing(['skor']);

        if ($pertandingan->skor->isEmpty()) {
            throw new RuntimeException('Tidak ada skor lama untuk diperbarui.');
        }

        $oldSets = $pertandingan->skor
            ->sortBy('set_ke')
            ->map(fn (PertandinganSkor $score) => [
                'skor_pemain1' => (int) $score->skor_pemain1,
                'skor_pemain2' => (int) $score->skor_pemain2,
            ])
            ->values()
            ->all();

        $oldResult = $this->calculateMatchResult(
            $oldSets,
            (int) $pertandingan->id_pemain1,
            (int) $pertandingan->id_pemain2
        );
        $newResult = $this->calculateMatchResult(
            $sets,
            (int) $pertandingan->id_pemain1,
            (int) $pertandingan->id_pemain2
        );

        return DB::transaction(function () use ($pertandingan, $sets, $oldResult, $newResult) {
            $this->applyFriendlyGroupStats(
                $pertandingan,
                $oldResult['winner_sets'],
                $oldResult['loser_sets'],
                $oldResult['winner_games'],
                $oldResult['loser_games'],
                true
            );

            $this->replaceMatchSets($pertandingan, $sets);

            $newWinnerPesertaId = $pertandingan->resolvePesertaIdForPemain($newResult['winner_id']);

            $pertandingan->update([
                'id_pemenang' => $newResult['winner_id'],
                'id_peserta_pemenang' => $newWinnerPesertaId,
                'status' => 'completed',
            ]);

            $this->applyFriendlyGroupStats(
                $pertandingan->fresh(),
                $newResult['winner_sets'],
                $newResult['loser_sets'],
                $newResult['winner_games'],
                $newResult['loser_games']
            );

            return $pertandingan->fresh([
                'skor', 'pemain1', 'pemain2', 'pemain1Partner', 'pemain2Partner',
                'pemenang', 'grup1', 'grup2',
            ]);
        });
    }

    protected function applyFriendlyGroupStats(
        Pertandingan $pertandingan,
        int $winnerSets,
        int $loserSets,
        int $winnerGames,
        int $loserGames,
        bool $reverse = false
    ): void {
        $winnerGrupId = $pertandingan->resolveWinnerGrupId();
        $loserGrupId = $pertandingan->resolveLoserGrupId();

        if (! $winnerGrupId || ! $loserGrupId) {
            throw new RuntimeException('Grup pemenang/kalah Friendly tidak dapat ditentukan.');
        }

        $winnerGrup = Grup::find($winnerGrupId);
        $loserGrup = Grup::find($loserGrupId);

        if (! $winnerGrup || ! $loserGrup) {
            throw new RuntimeException('Data grup Friendly tidak ditemukan.');
        }

        $sign = $reverse ? -1 : 1;

        $winnerGrup->increment('poin_didapat', 2 * $sign);
        $winnerGrup->increment('set_menang', $winnerSets * $sign);
        $winnerGrup->increment('games_menang', ($winnerGames - $loserGames) * $sign);

        $loserGrup->increment('set_menang', $loserSets * $sign);
        $loserGrup->increment('games_menang', ($loserGames - $winnerGames) * $sign);

        if (! $reverse) {
            $winnerGrup->fresh()->stampStatsReachedAt();
            $loserGrup->fresh()->stampStatsReachedAt();
        }
    }

    protected function updateCompletedKnockoutScore(Pertandingan $pertandingan, array $sets): Pertandingan
    {
        if (! $this->canEditKnockoutScore($pertandingan)) {
            throw new RuntimeException('Skor knockout tidak dapat diubah karena pertandingan berikutnya sudah dimainkan atau skor tidak valid untuk diedit.');
        }

        return $this->rewriteCompletedMatchScore($pertandingan, $sets, false);
    }

    protected function rewriteCompletedMatchScore(
        Pertandingan $pertandingan,
        array $sets,
        bool $isGroupMatch
    ): Pertandingan {
        if (! $pertandingan->id_pemain1 || ! $pertandingan->id_pemain2) {
            throw new RuntimeException('Kedua peserta harus sudah ditentukan sebelum mengubah skor.');
        }

        $pertandingan->loadMissing(['skor']);

        if ($pertandingan->skor->isEmpty()) {
            throw new RuntimeException('Tidak ada skor lama untuk diperbarui.');
        }

        $oldSets = $pertandingan->skor
            ->sortBy('set_ke')
            ->map(fn (PertandinganSkor $score) => [
                'skor_pemain1' => (int) $score->skor_pemain1,
                'skor_pemain2' => (int) $score->skor_pemain2,
            ])
            ->values()
            ->all();

        $oldResult = $this->calculateMatchResult(
            $oldSets,
            (int) $pertandingan->id_pemain1,
            (int) $pertandingan->id_pemain2
        );
        $newResult = $this->calculateMatchResult(
            $sets,
            (int) $pertandingan->id_pemain1,
            (int) $pertandingan->id_pemain2
        );

        return DB::transaction(function () use ($pertandingan, $sets, $oldResult, $newResult, $isGroupMatch) {
            $oldWinnerPesertaId = $pertandingan->id_peserta_pemenang
                ?: $pertandingan->resolvePesertaIdForPemain($oldResult['winner_id']);
            $oldLoserPesertaId = $pertandingan->resolvePesertaIdForPemain($oldResult['loser_id']);

            if ($isGroupMatch) {
                $this->applyGrupMemberStats(
                    (int) $pertandingan->id_grup,
                    $oldResult['winner_id'],
                    $oldResult['loser_id'],
                    $oldWinnerPesertaId,
                    $oldLoserPesertaId,
                    $oldResult['winner_sets'],
                    $oldResult['loser_sets'],
                    $oldResult['winner_games'],
                    $oldResult['loser_games'],
                    true
                );
            } else {
                $this->knockoutBracketService->clearAdvancementFrom($pertandingan);
            }

            $this->replaceMatchSets($pertandingan, $sets);

            $newWinnerPesertaId = $pertandingan->resolvePesertaIdForPemain($newResult['winner_id']);
            $newLoserPesertaId = $pertandingan->resolvePesertaIdForPemain($newResult['loser_id']);

            $pertandingan->update([
                'id_pemenang' => $newResult['winner_id'],
                'id_peserta_pemenang' => $newWinnerPesertaId,
                'status' => 'completed',
            ]);

            if ($isGroupMatch) {
                $this->applyGrupMemberStats(
                    (int) $pertandingan->id_grup,
                    $newResult['winner_id'],
                    $newResult['loser_id'],
                    $newWinnerPesertaId,
                    $newLoserPesertaId,
                    $newResult['winner_sets'],
                    $newResult['loser_sets'],
                    $newResult['winner_games'],
                    $newResult['loser_games']
                );
            } else {
                $freshForAdvance = $pertandingan->fresh();
                $this->knockoutBracketService->advanceWinner(
                    $freshForAdvance,
                    $newResult['winner_id'],
                    $newWinnerPesertaId
                );
                $this->knockoutBracketService->advanceLoser(
                    $freshForAdvance,
                    $newResult['loser_id'],
                    $newLoserPesertaId
                );
            }

            if ((int) $oldResult['winner_id'] !== (int) $newResult['winner_id']
                || (int) ($oldWinnerPesertaId ?? 0) !== (int) ($newWinnerPesertaId ?? 0)) {
                $fresh = $pertandingan->fresh();
                $this->pointRewardService->revokeMatchWin($fresh, $oldWinnerPesertaId, $oldResult['winner_id']);
                $this->pointRewardService->awardMatchWin($fresh);
            }

            return $pertandingan->fresh(['skor', 'pemain1', 'pemain2', 'peserta1', 'peserta2', 'pemenang', 'pesertaPemenang', 'grup']);
        });
    }

    public function calculateMatchResult(array $sets, int $pemain1Id, int $pemain2Id): array
    {
        if ($sets === []) {
            throw new RuntimeException('Minimal 1 set harus diisi.');
        }

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

        if ($setsWonP1 === $setsWonP2) {
            throw new RuntimeException('Pertandingan tidak boleh seri. Salah satu pemain harus memenangkan lebih banyak set.');
        }

        $p1Won = $setsWonP1 > $setsWonP2;

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

    protected function replaceMatchSets(Pertandingan $pertandingan, array $sets): void
    {
        $pertandingan->skor()->delete();

        foreach ($sets as $index => $set) {
            PertandinganSkor::create([
                'id_pertandingan' => $pertandingan->id,
                'set_ke' => $index + 1,
                'skor_pemain1' => $set['skor_pemain1'],
                'skor_pemain2' => $set['skor_pemain2'],
            ]);
        }
    }

    protected function applyGrupMemberStats(
        int $grupId,
        int $winnerId,
        int $loserId,
        ?int $winnerPesertaId,
        ?int $loserPesertaId,
        int $winnerSets,
        int $loserSets,
        int $winnerGames,
        int $loserGames,
        bool $reverse = false
    ): void {
        $winnerMember = $this->findGrupMember($grupId, $winnerId, $winnerPesertaId);
        $loserMember = $this->findGrupMember($grupId, $loserId, $loserPesertaId);

        if (! $winnerMember || ! $loserMember) {
            throw new RuntimeException('Kedua peserta harus terdaftar di grup untuk memperbarui klasemen.');
        }

        $sign = $reverse ? -1 : 1;

        $winnerMember->increment('poin_didapat', 2 * $sign);
        $winnerMember->increment('set_menang', $winnerSets * $sign);
        $winnerMember->increment('games_menang', ($winnerGames - $loserGames) * $sign);

        $loserMember->increment('set_menang', $loserSets * $sign);
        $loserMember->increment('games_menang', ($loserGames - $winnerGames) * $sign);

        if (! $reverse) {
            $winnerMember->fresh()->stampStatsReachedAt();
            $loserMember->fresh()->stampStatsReachedAt();
        }
    }

    /**
     * Rebuild padel group standings (poin/set/GD) from completed group match scores.
     */
    public function recalculateGroupStandingsForTurnamen(Turnamen $turnamen): void
    {
        if ($turnamen->isMahjong()) {
            return;
        }

        $turnamen->loadMissing(['grup']);

        foreach ($turnamen->grup as $grup) {
            $this->recalculateGroupStandingsForGrup((int) $grup->id);
        }
    }

    public function recalculateGroupStandingsForGrup(int $grupId): void
    {
        DB::transaction(function () use ($grupId) {
            GrupMember::where('id_grup', $grupId)->update([
                'poin_didapat' => 0,
                'set_menang' => 0,
                'games_menang' => 0,
                'stats_reached_at' => null,
            ]);

            $matches = Pertandingan::query()
                ->where('id_grup', $grupId)
                ->where('status', 'completed')
                ->with('skor')
                ->orderBy('id')
                ->get();

            foreach ($matches as $match) {
                if (! $match->id_pemain1 || ! $match->id_pemain2 || $match->skor->isEmpty()) {
                    continue;
                }

                $sets = $match->skor
                    ->sortBy('set_ke')
                    ->map(fn (PertandinganSkor $score) => [
                        'skor_pemain1' => (int) $score->skor_pemain1,
                        'skor_pemain2' => (int) $score->skor_pemain2,
                    ])
                    ->values()
                    ->all();

                $result = $this->calculateMatchResult(
                    $sets,
                    (int) $match->id_pemain1,
                    (int) $match->id_pemain2
                );

                $winnerPesertaId = $match->id_peserta_pemenang
                    ?: $match->resolvePesertaIdForPemain($result['winner_id']);
                $loserPesertaId = $match->resolvePesertaIdForPemain($result['loser_id']);

                $this->applyGrupMemberStats(
                    $grupId,
                    $result['winner_id'],
                    $result['loser_id'],
                    $winnerPesertaId,
                    $loserPesertaId,
                    $result['winner_sets'],
                    $result['loser_sets'],
                    $result['winner_games'],
                    $result['loser_games']
                );
            }
        });
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
