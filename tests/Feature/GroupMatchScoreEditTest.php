<?php

namespace Tests\Feature;

use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class GroupMatchScoreEditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_completed_group_score_can_be_edited_before_knockout(): void
    {
        $turnamen = $this->createOngoingSingleTournament();
        $entries = $this->createApprovedEntries($turnamen, 4);
        $service = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);

        $service->generateRandomGroups($turnamen, 2, 2);
        $service->generateGroupMatches($turnamen->fresh());

        $match = $turnamen->pertandingan()->whereNotNull('id_grup')->orderBy('id')->first();
        $this->assertNotNull($match);

        $scoring->recordScore($match, [
            ['skor_pemain1' => 6, 'skor_pemain2' => 2],
            ['skor_pemain1' => 6, 'skor_pemain2' => 3],
        ]);

        $match = $match->fresh(['skor']);
        $winnerBefore = (int) $match->id_pemenang;
        $loserBefore = $winnerBefore === (int) $match->id_pemain1
            ? (int) $match->id_pemain2
            : (int) $match->id_pemain1;

        $winnerMemberBefore = GrupMember::where('id_grup', $match->id_grup)
            ->where('id_pemain', $winnerBefore)
            ->first();

        $this->assertSame(2, (int) $winnerMemberBefore->poin_didapat);
        $this->assertTrue($scoring->canEditGroupScore($match));

        $flipped = [
            ['skor_pemain1' => 2, 'skor_pemain2' => 6],
            ['skor_pemain1' => 3, 'skor_pemain2' => 6],
        ];

        $updated = $scoring->recordScore($match->fresh(), $flipped);
        $this->assertSame($loserBefore, (int) $updated->id_pemenang);

        $oldWinner = GrupMember::where('id_grup', $match->id_grup)
            ->where('id_pemain', $winnerBefore)
            ->first();
        $newWinner = GrupMember::where('id_grup', $match->id_grup)
            ->where('id_pemain', $loserBefore)
            ->first();

        $this->assertSame(0, (int) $oldWinner->poin_didapat);
        $this->assertSame(2, (int) $newWinner->poin_didapat);
        $this->assertSame(2, $updated->skor()->count());
    }

    public function test_completed_group_score_cannot_be_edited_after_knockout_exists(): void
    {
        $turnamen = $this->createOngoingSingleTournament();
        $this->createApprovedEntries($turnamen, 4);
        $service = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);

        $service->generateRandomGroups($turnamen, 2, 2);
        $service->generateGroupMatches($turnamen->fresh());

        foreach ($turnamen->pertandingan()->whereNotNull('id_grup')->get() as $match) {
            $scoring->recordScore($match->fresh(), [
                ['skor_pemain1' => 6, 'skor_pemain2' => 1],
                ['skor_pemain1' => 6, 'skor_pemain2' => 2],
            ]);
        }

        app(\App\Services\KnockoutBracketService::class)->generateKnockoutBracket($turnamen->fresh(), 1);

        $groupMatch = $turnamen->pertandingan()->whereNotNull('id_grup')->first();
        $this->assertFalse($scoring->canEditGroupScore($groupMatch->fresh()));

        $this->expectException(RuntimeException::class);
        $scoring->recordScore($groupMatch->fresh(), [
            ['skor_pemain1' => 1, 'skor_pemain2' => 6],
            ['skor_pemain1' => 2, 'skor_pemain2' => 6],
        ]);
    }

    protected function createOngoingSingleTournament(): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Score Edit Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'ongoing',
            'group_matches_generated_at' => null,
        ]);
    }

    protected function createApprovedEntries(Turnamen $turnamen, int $count)
    {
        return collect(range(1, $count))->map(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "Score Edit Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0813' . str_pad((string) $index . random_int(10, 99), 8, '0', STR_PAD_LEFT),
                'rating' => 3 + ($index / 10),
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
