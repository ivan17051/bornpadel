<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\MatchScoringService;
use App\Services\TournamentCompletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TournamentCompleteAfterFinalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_complete_after_final_even_if_third_place_is_pending(): void
    {
        [$turnamen, $scoring] = $this->prepareFinalCompletedWithPendingThirdPlace();
        $completion = app(TournamentCompletionService::class);

        $this->assertTrue($completion->canComplete($turnamen->fresh()));
        $this->assertTrue($completion->hasPendingThirdPlacePlayoff($turnamen->fresh()));

        $thirdPlace = Pertandingan::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('nama_ronde', 'Perebutan Juara 3')
            ->first();

        $this->assertNotNull($thirdPlace);
        $this->assertSame('scheduled', $thirdPlace->status);

        $result = $completion->complete($turnamen->fresh());

        $this->assertSame('completed', $turnamen->fresh()->status);
        $this->assertTrue($result['cancelled_third_place']);
        $this->assertSame('cancelled', $thirdPlace->fresh()->status);
        $this->assertNotEmpty($result['placements'][1] ?? []);
        $this->assertNotEmpty($result['placements'][2] ?? []);
        $this->assertFalse($scoring->canEditKnockoutScore($thirdPlace->fresh()));
    }

    /**
     * @return array{0: Turnamen, 1: MatchScoringService}
     */
    protected function prepareFinalCompletedWithPendingThirdPlace(): array
    {
        $turnamen = Turnamen::create([
            'nama' => 'Complete After Final ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'ongoing',
        ]);

        collect(range(1, 8))->each(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "CAF Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0816' . str_pad((string) $index . random_int(10, 99), 8, '0', STR_PAD_LEFT),
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

        $groups = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);
        $bracket = app(KnockoutBracketService::class);

        $groups->generateRandomGroups($turnamen, 2, 2);
        $groups->generateGroupMatches($turnamen->fresh());

        foreach ($turnamen->pertandingan()->whereNotNull('id_grup')->get() as $match) {
            $scoring->recordScore($match->fresh(), [
                ['skor_pemain1' => 6, 'skor_pemain2' => 1],
                ['skor_pemain1' => 6, 'skor_pemain2' => 2],
            ]);
        }

        // 8 players / 4 groups of 2 → 4 knockout qualifiers → SF + Final + Juara 3.
        $bracket->generateKnockoutBracket($turnamen->fresh(), 1);

        foreach (
            $turnamen->pertandingan()
                ->whereNull('id_grup')
                ->where('nama_ronde', 'Semifinal')
                ->orderBy('id')
                ->get() as $match
        ) {
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
        $scoring->recordScore($final->fresh(), [
            ['skor_pemain1' => 6, 'skor_pemain2' => 3],
            ['skor_pemain1' => 6, 'skor_pemain2' => 4],
        ]);

        return [$turnamen->fresh(), $scoring];
    }
}
