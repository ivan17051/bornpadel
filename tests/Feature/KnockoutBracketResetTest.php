<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPasangan;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class KnockoutBracketResetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_knockout_bracket_can_be_reset_before_scores_and_end_group_stage_unlocks(): void
    {
        [$turnamen, $scoring, $bracket] = $this->prepareKnockoutBracketWithoutScores();

        $this->assertTrue($bracket->hasKnockoutBracket($turnamen));
        $this->assertTrue($bracket->canResetKnockoutBracket($turnamen));
        $this->assertFalse($bracket->hasKnockoutScores($turnamen));
        $this->assertFalse($bracket->canEndGroupStage($turnamen));

        $before = Pertandingan::query()
            ->where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->count();
        $this->assertGreaterThan(0, $before);

        $result = $bracket->resetKnockoutBracket($turnamen->fresh());

        $this->assertSame($before, $result['deleted']);
        $this->assertFalse($result['had_scores']);
        $this->assertFalse($bracket->hasKnockoutBracket($turnamen->fresh()));
        $this->assertTrue($bracket->canEndGroupStage($turnamen->fresh()));
        $this->assertSame(
            0,
            Pertandingan::query()
                ->where('id_turnamen', $turnamen->id)
                ->whereNull('id_grup')
                ->count()
        );
        $this->assertGreaterThan(
            0,
            Pertandingan::query()
                ->where('id_turnamen', $turnamen->id)
                ->whereNotNull('id_grup')
                ->count()
        );
    }

    public function test_knockout_bracket_can_be_reset_after_scores_and_revokes_match_win_points(): void
    {
        [$turnamen, $scoring, $bracket] = $this->prepareKnockoutBracketWithoutScores();

        $firstRound = Pertandingan::query()
            ->where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Semifinal')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($firstRound);

        $side1 = (int) $firstRound->id_pemain1;
        $side2 = (int) $firstRound->id_pemain2;
        $pointsBefore = [
            $side1 => (int) Pemain::findOrFail($side1)->total_poin,
            $side2 => (int) Pemain::findOrFail($side2)->total_poin,
        ];

        $scoring->recordScore($firstRound, [
            ['skor_pemain1' => 6, 'skor_pemain2' => 1],
            ['skor_pemain1' => 6, 'skor_pemain2' => 2],
        ]);

        $winnerId = (int) $firstRound->fresh()->id_pemenang;
        $winner = Pemain::findOrFail($winnerId);
        $this->assertGreaterThan($pointsBefore[$winnerId], (int) $winner->total_poin);
        $this->assertTrue($bracket->hasKnockoutScores($turnamen->fresh()));
        $this->assertTrue($bracket->canResetKnockoutBracket($turnamen->fresh()));

        $result = $bracket->resetKnockoutBracket($turnamen->fresh());

        $this->assertTrue($result['had_scores']);
        $this->assertGreaterThanOrEqual(1, $result['revoked_wins']);
        $this->assertSame($pointsBefore[$winnerId], (int) $winner->fresh()->total_poin);
        $this->assertFalse($bracket->hasKnockoutBracket($turnamen->fresh()));
        $this->assertTrue($bracket->canEndGroupStage($turnamen->fresh()));
    }

    public function test_reset_bracket_with_scores_requires_valid_password(): void
    {
        [$turnamen, $scoring] = $this->prepareKnockoutBracketWithoutScores();

        $user = User::create([
            'name' => 'Reset Bracket Admin',
            'username' => 'reset-bracket-' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => Hash::make('secret-pass'),
            'role' => 'admin',
        ]);

        $firstRound = Pertandingan::query()
            ->where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Semifinal')
            ->orderBy('id')
            ->first();

        $scoring->recordScore($firstRound, [
            ['skor_pemain1' => 6, 'skor_pemain2' => 1],
            ['skor_pemain1' => 6, 'skor_pemain2' => 2],
        ]);

        $this->actingAs($user)
            ->deleteJson(route('admin.matchmaking.reset-bracket'), [
                'id_turnamen' => $turnamen->id,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->deleteJson(route('admin.matchmaking.reset-bracket'), [
                'id_turnamen' => $turnamen->id,
                'password' => 'wrong-pass',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Password tidak valid.']);

        $this->actingAs($user)
            ->deleteJson(route('admin.matchmaking.reset-bracket'), [
                'id_turnamen' => $turnamen->id,
                'password' => 'secret-pass',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse(app(KnockoutBracketService::class)->hasKnockoutBracket($turnamen->fresh()));
    }

    public function test_reset_is_blocked_after_tournament_completed(): void
    {
        [$turnamen] = $this->prepareKnockoutBracketWithoutScores();
        $turnamen->update(['status' => 'completed']);
        $bracket = app(KnockoutBracketService::class);

        $this->assertFalse($bracket->canResetKnockoutBracket($turnamen->fresh()));
        $this->expectException(RuntimeException::class);
        $bracket->resetKnockoutBracket($turnamen->fresh());
    }

    /**
     * @return array{0: Turnamen, 1: MatchScoringService, 2: KnockoutBracketService}
     */
    protected function prepareKnockoutBracketWithoutScores(): array
    {
        $turnamen = Turnamen::create([
            'nama' => 'Bracket Reset Test ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => 16,
            'jenis' => 'single',
            'status' => 'ongoing',
        ]);

        collect(range(1, 16))->each(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "BR Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0817' . str_pad((string) $index . random_int(10, 99), 8, '0', STR_PAD_LEFT),
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

        $peserta = TurnamenPeserta::query()->forTurnamen($turnamen->id)->orderBy('id')->get();
        foreach ($peserta->chunk(2) as $pair) {
            $pair = $pair->values();
            TurnamenPasangan::create([
                'id_turnamen' => $turnamen->id,
                'id_peserta_1' => $pair[0]->id,
                'id_peserta_2' => $pair[1]->id,
                'paired_at' => now(),
            ]);
        }
        $turnamen->update(['registration_paired_at' => now()]);

        $groups = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);
        $bracket = app(KnockoutBracketService::class);

        $groups->generateRandomGroups($turnamen->fresh(), 2, 2);
        $groups->generateGroupMatches($turnamen->fresh());

        foreach ($turnamen->pertandingan()->whereNotNull('id_grup')->get() as $match) {
            $scoring->recordScore($match->fresh(), [
                ['skor_pemain1' => 6, 'skor_pemain2' => 1],
                ['skor_pemain1' => 6, 'skor_pemain2' => 2],
            ]);
        }

        $bracket->generateKnockoutBracket($turnamen->fresh(), 1);

        return [$turnamen->fresh(), $scoring, $bracket];
    }
}
