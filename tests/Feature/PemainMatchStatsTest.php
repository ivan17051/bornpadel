<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\PertandinganSkor;
use App\Models\Turnamen;
use App\Models\TurnamenPasangan;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\MatchScoringService;
use App\Services\PemainMatchStatsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PemainMatchStatsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_career_stats_count_completed_scored_matches_and_partner_wins(): void
    {
        $turnamen = $this->createOngoingSingleTournament();
        $entries = $this->createApprovedEntries($turnamen, 4);
        $service = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);
        $stats = app(PemainMatchStatsService::class);

        $service->generateRandomGroups($turnamen, 2, 2);
        $service->generateGroupMatches($turnamen->fresh());

        $match = $turnamen->pertandingan()->whereNotNull('id_grup')->orderBy('id')->first();
        $this->assertNotNull($match);

        $scoring->recordScore($match, [
            ['skor_pemain1' => 6, 'skor_pemain2' => 2],
            ['skor_pemain1' => 6, 'skor_pemain2' => 3],
        ]);

        $match = $match->fresh();
        $winnerPesertaId = (int) $match->id_peserta_pemenang;
        $winnerPeserta = TurnamenPeserta::findOrFail($winnerPesertaId);
        $winnerIds = $winnerPeserta->pemainIds();
        $this->assertCount(2, $winnerIds);

        foreach ($winnerIds as $pemainId) {
            $career = $stats->getCareerStats($pemainId);
            $this->assertSame(1, $career['played'], 'Winner side should count played');
            $this->assertSame(1, $career['won'], 'Both pair members should get the win');
        }

        $loserPesertaId = (int) $match->id_peserta1 === $winnerPesertaId
            ? (int) $match->id_peserta2
            : (int) $match->id_peserta1;
        $loserIds = TurnamenPeserta::findOrFail($loserPesertaId)->pemainIds();

        foreach ($loserIds as $pemainId) {
            $career = $stats->getCareerStats($pemainId);
            $this->assertSame(1, $career['played']);
            $this->assertSame(0, $career['won']);
        }
    }

    public function test_bye_matches_without_scores_are_excluded(): void
    {
        $turnamen = $this->createOngoingSingleTournament();
        $entries = $this->createApprovedEntries($turnamen, 2);
        $peserta = $entries->first();
        $pemainId = (int) $peserta->id_pemain1;

        Pertandingan::create([
            'id_turnamen' => $turnamen->id,
            'nama_ronde' => 'Babak 16 Besar',
            'id_pemain1' => $pemainId,
            'id_peserta1' => $peserta->id,
            'id_pemain2' => null,
            'id_peserta2' => null,
            'id_pemenang' => $pemainId,
            'id_peserta_pemenang' => $peserta->id,
            'status' => 'completed',
        ]);

        $career = app(PemainMatchStatsService::class)->getCareerStats($pemainId);

        $this->assertSame(0, $career['played']);
        $this->assertSame(0, $career['won']);
    }

    public function test_friendly_partner_counts_as_win_via_side(): void
    {
        $turnamen = Turnamen::create([
            'nama' => 'Friendly Stats ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 0,
            'maks_peserta' => 16,
            'jenis' => 'friendly',
            'status' => 'ongoing',
            'players_per_group' => 4,
        ]);

        $players = collect(range(1, 4))->map(function ($index) {
            return Pemain::create([
                'nama' => "Friendly Stats P{$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0817' . str_pad((string) ($index . random_int(10, 99)), 8, '0', STR_PAD_LEFT),
                'rating' => 3.0,
                'total_poin' => 0,
            ]);
        })->values();

        $match = Pertandingan::create([
            'id_turnamen' => $turnamen->id,
            'nama_ronde' => 'Friendly',
            'id_pemain1' => $players[0]->id,
            'id_pemain1_partner' => $players[1]->id,
            'id_pemain2' => $players[2]->id,
            'id_pemain2_partner' => $players[3]->id,
            'id_pemenang' => $players[0]->id,
            'status' => 'completed',
        ]);

        PertandinganSkor::create([
            'id_pertandingan' => $match->id,
            'set_ke' => 1,
            'skor_pemain1' => 6,
            'skor_pemain2' => 1,
        ]);
        PertandinganSkor::create([
            'id_pertandingan' => $match->id,
            'set_ke' => 2,
            'skor_pemain1' => 6,
            'skor_pemain2' => 2,
        ]);

        $stats = app(PemainMatchStatsService::class);

        $this->assertSame(['played' => 1, 'won' => 1], $stats->getCareerStats($players[0]->id));
        $this->assertSame(['played' => 1, 'won' => 1], $stats->getCareerStats($players[1]->id));
        $this->assertSame(['played' => 1, 'won' => 0], $stats->getCareerStats($players[2]->id));
    }

    public function test_guest_profile_shows_career_stats(): void
    {
        $pemain = Pemain::create([
            'nama' => 'Profile Stats ' . uniqid(),
            'gender' => 'male',
            'no_hp' => '0818' . str_pad((string) random_int(10000000, 99999999), 8, '0'),
            'rating' => 3.5,
            'total_poin' => 12,
        ]);

        $response = $this->get(route('guest.pemain.show', $pemain));

        $response->assertOk();
        $response->assertSee('Pertandingan');
        $response->assertSee('Menang');
        $response->assertSee('Total Poin');
    }

    protected function createOngoingSingleTournament(): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Match Stats Test ' . uniqid(),
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
        $entries = collect(range(1, $count))->map(function ($index) use ($turnamen) {
            $pemain = Pemain::create([
                'nama' => "Match Stats Player {$index} " . uniqid(),
                'gender' => $index % 2 ? 'male' : 'female',
                'no_hp' => '0819' . str_pad((string) $index . random_int(10, 99), 8, '0', STR_PAD_LEFT),
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

        foreach ($entries->chunk(2) as $pair) {
            $pair = $pair->values();
            TurnamenPasangan::create([
                'id_turnamen' => $turnamen->id,
                'id_peserta_1' => $pair[0]->id,
                'id_peserta_2' => $pair[1]->id,
                'paired_at' => now(),
            ]);
        }

        $turnamen->update(['registration_paired_at' => now()]);

        return $entries;
    }
}
