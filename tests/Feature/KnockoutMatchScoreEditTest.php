<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class KnockoutMatchScoreEditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_knockout_score_can_be_edited_when_downstream_is_unplayed(): void
    {
        [$turnamen, $scoring] = $this->prepareKnockoutReadyTournament();

        $firstRound = $turnamen->pertandingan()
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Semifinal')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($firstRound);
        $this->assertTrue($firstRound->isReadyForScoring());

        $scoring->recordScore($firstRound, [
            ['skor_pemain1' => 6, 'skor_pemain2' => 1],
            ['skor_pemain1' => 6, 'skor_pemain2' => 2],
        ]);

        $firstRound = $firstRound->fresh(['skor']);
        $this->assertTrue($scoring->canEditKnockoutScore($firstRound));

        $oldWinner = (int) $firstRound->id_pemenang;
        $newWinner = $oldWinner === (int) $firstRound->id_pemain1
            ? (int) $firstRound->id_pemain2
            : (int) $firstRound->id_pemain1;

        $updated = $scoring->recordScore($firstRound->fresh(), [
            ['skor_pemain1' => 1, 'skor_pemain2' => 6],
            ['skor_pemain1' => 2, 'skor_pemain2' => 6],
        ]);

        $this->assertSame($newWinner, (int) $updated->id_pemenang);

        $next = \App\Models\Pertandingan::find($updated->id_next_pertandingan);
        $this->assertNotNull($next);
        $this->assertTrue(
            (int) $next->id_pemain1 === $newWinner || (int) $next->id_pemain2 === $newWinner
        );
    }

    public function test_knockout_score_cannot_be_edited_after_downstream_is_played(): void
    {
        [$turnamen, $scoring] = $this->prepareKnockoutReadyTournament();

        $matches = $turnamen->pertandingan()
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Semifinal')
            ->orderBy('id')
            ->get();

        foreach ($matches as $match) {
            $scoring->recordScore($match->fresh(), [
                ['skor_pemain1' => 6, 'skor_pemain2' => 0],
                ['skor_pemain1' => 6, 'skor_pemain2' => 1],
            ]);
        }

        $final = $turnamen->pertandingan()
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Final')
            ->first();

        $this->assertNotNull($final);
        $this->assertTrue($final->isReadyForScoring());

        $scoring->recordScore($final->fresh(), [
            ['skor_pemain1' => 6, 'skor_pemain2' => 3],
            ['skor_pemain1' => 6, 'skor_pemain2' => 4],
        ]);

        $feeder = $matches->first()->fresh();
        $this->assertFalse($scoring->canEditKnockoutScore($feeder));

        $this->expectException(RuntimeException::class);
        $scoring->recordScore($feeder, [
            ['skor_pemain1' => 0, 'skor_pemain2' => 6],
            ['skor_pemain1' => 1, 'skor_pemain2' => 6],
        ]);
    }

    /**
     * @return array{0: Turnamen, 1: MatchScoringService}
     */
    protected function prepareKnockoutReadyTournament(): array
    {
        $turnamen = Turnamen::create([
            'nama' => 'KO Score Edit ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'ongoing',
        ]);

        collect(range(1, 8))->each(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "KO Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0814' . str_pad((string) ($index . random_int(10, 99)), 8, '0', STR_PAD_LEFT),
                'rating' => 3 + ($index / 10),
                'total_poin' => 0,
            ]);

            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        });

        $groupService = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);
        $bracket = app(KnockoutBracketService::class);

        $groupService->generateRandomGroups($turnamen, 2, 2);
        $groupService->generateGroupMatches($turnamen->fresh());

        foreach ($turnamen->pertandingan()->whereNotNull('id_grup')->get() as $match) {
            $scoring->recordScore($match->fresh(), [
                ['skor_pemain1' => 6, 'skor_pemain2' => 2],
                ['skor_pemain1' => 6, 'skor_pemain2' => 3],
            ]);
        }

        // 8 players / 4 groups of 2 → 4 knockout qualifiers → Semifinal + Final.
        $bracket->generateKnockoutBracket($turnamen->fresh(), 1);

        return [$turnamen->fresh(), $scoring];
    }
}
