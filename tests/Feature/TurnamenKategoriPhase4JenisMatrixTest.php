<?php

namespace Tests\Feature;

use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use App\Models\TurnamenPeserta;
use App\Services\DoublePairingService;
use App\Services\FriendlyMatchmakingService;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\LeaderboardService;
use App\Services\MahjongMatchmakingService;
use App\Services\MatchScoringService;
use App\Services\TurnamenKategoriService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 4: multi-category isolation & jenis regression matrix.
 */
class TurnamenKategoriPhase4JenisMatrixTest extends TestCase
{
    use DatabaseTransactions;

    // ─── Single / Double ───────────────────────────────────────────

    public function test_single_odd_count_blocked_per_category_not_event_wide(): void
    {
        $turnamen = $this->createEvent('single', 'open');
        $katA = $turnamen->defaultKategori();
        $katB = $this->addCategory($turnamen, 'Open B');

        // Event-wide would be 5+5 even; each category has 5 (odd).
        $this->seedApprovedSolos($turnamen, $katA->id, 5);
        $this->seedApprovedSolos($turnamen, $katB->id, 5);

        $service = app(GroupMatchmakingService::class);

        $this->assertFalse($service->canCloseRegistration($turnamen, $katA->id));
        $this->assertFalse($service->canCloseRegistration($turnamen, $katB->id));

        try {
            $service->closeRegistration($turnamen, $katA->id);
            $this->fail('Expected odd-count failure for category A');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ganjil', $e->getMessage());
        }

        $this->assertSame('open', $katA->fresh()->status);
        $this->assertSame('open', $katB->fresh()->status);

        // Make A even; B still odd — A can close independently.
        $this->seedApprovedSolos($turnamen, $katA->id, 1);
        $this->assertTrue($service->canCloseRegistration($turnamen->fresh(), $katA->id));
        $service->closeRegistration($turnamen->fresh(), $katA->id);

        $this->assertSame('ongoing', $katA->fresh()->status);
        $this->assertSame('open', $katB->fresh()->status);
        $this->assertFalse($service->canCloseRegistration($turnamen->fresh(), $katB->id));
    }

    public function test_double_unpaired_solo_only_blocks_own_category(): void
    {
        $turnamen = $this->createEvent('double', 'open');
        $katA = $turnamen->defaultKategori();
        $katB = $this->addCategory($turnamen, 'Double B');

        $entriesA = $this->seedApprovedSolos($turnamen, $katA->id, 4);
        $pairing = app(DoublePairingService::class);
        $pairing->createPair($turnamen, $entriesA[0], $entriesA[1], $katA->id);
        $pairing->createPair($turnamen, $entriesA[2], $entriesA[3], $katA->id);

        // B has one approved pair + one solo → cannot close B.
        $entriesB = $this->seedApprovedSolos($turnamen, $katB->id, 3);
        $pairing->createPair($turnamen, $entriesB[0], $entriesB[1], $katB->id);

        $service = app(GroupMatchmakingService::class);

        $this->assertTrue($service->canCloseRegistration($turnamen, $katA->id));
        $service->closeRegistration($turnamen->fresh(), $katA->id);
        $this->assertSame('ongoing', $katA->fresh()->status);

        $this->assertFalse($service->canCloseRegistration($turnamen->fresh(), $katB->id));
        try {
            $service->closeRegistration($turnamen->fresh(), $katB->id);
            $this->fail('Expected unpaired solo failure for category B');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tanpa pasangan', $e->getMessage());
        }

        $this->assertSame('open', $katB->fresh()->status);
        // A pairings intact
        $this->assertSame(2, $turnamen->competitionPeserta($katA->id)->whereHas('pasanganAsPeserta1')->count());
    }

    public function test_single_rr_and_knockout_seed_isolated_per_category(): void
    {
        $turnamen = $this->createEvent('single', 'open', 32);
        $katA = $turnamen->defaultKategori();
        $katB = $this->addCategory($turnamen, 'Knockout B');

        $this->seedApprovedSolos($turnamen, $katA->id, 8);
        $this->seedApprovedSolos($turnamen, $katB->id, 8);

        $groups = app(GroupMatchmakingService::class);
        $scoring = app(MatchScoringService::class);
        $bracket = app(KnockoutBracketService::class);

        $groups->closeRegistration($turnamen->fresh(), $katA->id);
        $groups->closeRegistration($turnamen->fresh(), $katB->id);
        $groups->generateRandomGroups($turnamen->fresh(), 2, 2, 'random', $katA->id);
        $groups->generateRandomGroups($turnamen->fresh(), 2, 2, 'random', $katB->id);
        $groups->generateGroupMatches($turnamen->fresh(), $katA->id);
        $groups->generateGroupMatches($turnamen->fresh(), $katB->id);

        $matchesA = Pertandingan::query()
            ->where('id_kategori', $katA->id)
            ->where('nama_ronde', 'Fase Grup')
            ->count();
        $matchesB = Pertandingan::query()
            ->where('id_kategori', $katB->id)
            ->where('nama_ronde', 'Fase Grup')
            ->count();

        $this->assertGreaterThan(0, $matchesA);
        $this->assertGreaterThan(0, $matchesB);
        $this->assertSame($matchesA, $matchesB);

        $this->scoreAllGroupMatches($turnamen, $katA->id, $scoring);
        $this->scoreAllGroupMatches($turnamen, $katB->id, $scoring);

        $resultA = $bracket->generateKnockoutBracket(
            $turnamen->fresh(),
            4,
            KnockoutBracketService::QUALIFICATION_TOTAL,
            $katA->id
        );
        $this->assertGreaterThan(0, $resultA['qualifiers'] ?? $resultA['bracket_size'] ?? 0);

        $this->assertFalse($bracket->hasKnockoutBracket($turnamen->fresh(), $katB->id));
        $this->assertTrue($bracket->hasKnockoutBracket($turnamen->fresh(), $katA->id));

        $bracket->generateKnockoutBracket(
            $turnamen->fresh(),
            4,
            KnockoutBracketService::QUALIFICATION_TOTAL,
            $katB->id
        );

        $koA = Pertandingan::query()
            ->where('id_kategori', $katA->id)
            ->whereNull('id_grup')
            ->count();
        $koB = Pertandingan::query()
            ->where('id_kategori', $katB->id)
            ->whereNull('id_grup')
            ->count();

        $this->assertGreaterThan(0, $koA);
        $this->assertGreaterThan(0, $koB);
        $this->assertEmpty(
            array_intersect(
                Pertandingan::where('id_kategori', $katA->id)->whereNull('id_grup')->pluck('id')->all(),
                Pertandingan::where('id_kategori', $katB->id)->whereNull('id_grup')->pluck('id')->all()
            )
        );
    }

    public function test_multi_category_can_generate_groups_while_event_still_open(): void
    {
        // multi-cat close does not mirror status onto event shell
        $turnamen = $this->createEvent('single', 'open');
        $katA = $turnamen->defaultKategori();
        $this->addCategory($turnamen, 'Still Open');

        $this->seedApprovedSolos($turnamen, $katA->id, 8);
        $service = app(GroupMatchmakingService::class);
        $service->closeRegistration($turnamen->fresh(), $katA->id);

        $this->assertSame('open', $turnamen->fresh()->status);
        $this->assertSame('ongoing', $katA->fresh()->status);
        $this->assertTrue($service->canGenerateRandomGroups($turnamen->fresh(), $katA->id));

        $service->generateRandomGroups($turnamen->fresh(), 2, 2, 'random', $katA->id);
        $this->assertSame(2, $turnamen->competitionGrup($katA->id)->count());
    }

    // ─── Mahjong ───────────────────────────────────────────────────

    public function test_mahjong_groups_and_final_flag_are_per_category(): void
    {
        $turnamen = $this->createEvent('mahjong', 'open');
        $katA = $turnamen->defaultKategori();
        $katB = $this->addCategory($turnamen, 'Mahjong B');

        $this->seedApprovedSolos($turnamen, $katA->id, 8);
        $this->seedApprovedSolos($turnamen, $katB->id, 8);

        // Close each category to ongoing via group matchmaking lifecycle for mahjong
        // Mahjong typically becomes ongoing when registration closes through admin -
        // set kategori status manually then generate.
        $katA->update(['status' => 'ongoing']);
        $katB->update(['status' => 'ongoing']);
        // Keep event shell open on purpose (multi-cat pattern)
        $turnamen->update(['status' => 'open']);

        $mahjong = app(MahjongMatchmakingService::class);

        $this->assertTrue($mahjong->canGenerateGroups($turnamen->fresh(), $katA->id));
        $mahjong->generateGroups($turnamen->fresh(), 'random', $katA->id);

        $this->assertSame(2, $turnamen->competitionGrup($katA->id)->count());
        $this->assertSame(0, $turnamen->competitionGrup($katB->id)->count());

        $mahjong->generateGroups($turnamen->fresh(), 'random', $katB->id);
        $this->assertSame(2, $turnamen->competitionGrup($katA->id)->count());
        $this->assertSame(2, $turnamen->competitionGrup($katB->id)->count());

        foreach ($turnamen->competitionGrup($katA->id)->get() as $grup) {
            $this->assertSame((int) $katA->id, (int) $grup->id_kategori);
            $this->assertSame(1, (int) $grup->babak);
        }

        // Reshuffle A only
        $idsABefore = $turnamen->competitionGrup($katA->id)->orderBy('id')->pluck('id')->all();
        $mahjong->reshuffleGroups($turnamen->fresh(), 'random', $katA->id);
        $idsAAfter = $turnamen->competitionGrup($katA->id)->orderBy('id')->pluck('id')->all();
        $this->assertNotEquals($idsABefore, $idsAAfter);
        $this->assertSame(2, $turnamen->competitionActiveGrup($katB->id)->count());

        // Mark A final independently
        $katA->refresh()->update(['mahjong_is_final' => true]);
        $this->assertTrue((bool) $katA->fresh()->mahjong_is_final);
        $this->assertFalse((bool) $katB->fresh()->mahjong_is_final);
        $this->assertFalse($mahjong->canReshuffle($turnamen->fresh(), $katA->id));
        $this->assertTrue($mahjong->canReshuffle($turnamen->fresh(), $katB->id));

        $standingsA = app(LeaderboardService::class)->getMahjongStandingsByBabak($turnamen->id, $katA->id);
        $standingsB = app(LeaderboardService::class)->getMahjongStandingsByBabak($turnamen->id, $katB->id);
        $this->assertFalse($standingsA['sections']->isEmpty());
        $this->assertFalse($standingsB['sections']->isEmpty());
    }

    // ─── Friendly ──────────────────────────────────────────────────

    public function test_friendly_slots_and_parallel_sessions_scoped_per_category(): void
    {
        $turnamen = $this->createEvent('friendly', 'open', 48);
        $katA = $turnamen->defaultKategori();
        $katB = $this->addCategory($turnamen, 'Friendly B', [
            'players_per_group' => 4,
        ]);

        $this->seedApprovedSolos($turnamen, $katA->id, 8);
        $this->seedApprovedSolos($turnamen, $katB->id, 24); // 6 groups, parallel sessions

        $katA->update(['status' => 'ongoing']);
        $katB->update(['status' => 'ongoing']);
        $turnamen->update(['status' => 'open']); // multi-cat: event may stay open

        $friendly = app(FriendlyMatchmakingService::class);

        $resultA = $friendly->generateGroups($turnamen->fresh(), 'random', $katA->id);
        $this->assertSame(2, $resultA['group_count']);
        $this->assertSame(1, $resultA['match_slots']);
        $this->assertSame(0, $turnamen->competitionGrup($katB->id)->count());

        $resultB = $friendly->generateGroups($turnamen->fresh(), 'random', $katB->id);
        $this->assertSame(6, $resultB['group_count']);
        $this->assertSame(15, $resultB['match_slots']);

        $slotA = Pertandingan::query()
            ->where('id_kategori', $katA->id)
            ->where('nama_ronde', 'Friendly')
            ->count();
        $slotB = Pertandingan::query()
            ->where('id_kategori', $katB->id)
            ->where('nama_ronde', 'Friendly')
            ->count();

        $this->assertSame(1, $slotA);
        $this->assertSame(15, $slotB);

        foreach (Pertandingan::where('id_kategori', $katA->id)->get() as $match) {
            $this->assertSame((int) $katA->id, (int) $match->id_kategori);
        }

        $matchesB = $friendly->getMatches($turnamen->fresh(), $katB->id);
        $this->assertSame(15, $matchesB->count());
        $sessionOne = $matchesB->where('parallel_round', 1)->values();
        $this->assertSame(3, $sessionOne->count());

        foreach ($matchesB->groupBy('parallel_round') as $roundMatches) {
            $groupIds = $roundMatches->flatMap(function ($m) {
                return [(int) $m->id_grup1, (int) $m->id_grup2];
            })->all();
            $this->assertSame(count($groupIds), count(array_unique($groupIds)));
        }

        // Completing a slot in A does not block reset of B
        $slot = Pertandingan::where('id_kategori', $katA->id)->first();
        $g1 = $slot->grup1->members()->pluck('id_pemain')->map(function ($id) {
            return (int) $id;
        })->take(2)->all();
        $g2 = $slot->grup2->members()->pluck('id_pemain')->map(function ($id) {
            return (int) $id;
        })->take(2)->all();
        $friendly->assignPairs($slot, $g1, $g2);

        $this->assertTrue($friendly->canReset($turnamen->fresh(), $katB->id));
        $friendly->resetGroupsAndMatches($turnamen->fresh(), $katB->id);
        $this->assertSame(0, $turnamen->competitionGrup($katB->id)->count());
        $this->assertSame(2, $turnamen->competitionGrup($katA->id)->count());
    }

    public function test_friendly_registration_groups_do_not_cross_categories(): void
    {
        $turnamen = $this->createEvent('friendly', 'open');
        $katA = $turnamen->defaultKategori();
        $katB = $this->addCategory($turnamen, 'PreGroup B');

        app(\App\Services\PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Team Alpha A',
            $this->playerPayloads(4, 'A'),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            true,
            'approved',
            null,
            $katA->id
        );

        app(\App\Services\PemainRegistrationService::class)->registerGroup(
            $turnamen,
            'Team Beta B',
            $this->playerPayloads(4, 'B'),
            [],
            null,
            TurnamenPeserta::SUMBER_INTERNAL,
            true,
            'approved',
            null,
            $katB->id
        );

        $friendly = app(FriendlyMatchmakingService::class);
        $regA = $friendly->getFriendlyRegistrationGroups($turnamen, $katA->id);
        $regB = $friendly->getFriendlyRegistrationGroups($turnamen, $katB->id);

        $namesA = $regA->pluck('nama')->filter()->all();
        $namesB = $regB->pluck('nama')->filter()->all();

        $this->assertContains('Team Alpha A', $namesA);
        $this->assertNotContains('Team Beta B', $namesA);
        $this->assertContains('Team Beta B', $namesB);
        $this->assertNotContains('Team Alpha A', $namesB);
    }

    // ─── helpers ───────────────────────────────────────────────────

    protected function createEvent(string $jenis, string $status = 'open', int $maks = 32): Turnamen
    {
        return Turnamen::create([
            'nama' => 'Phase4 ' . $jenis . ' ' . uniqid(),
            'tanggal' => now()->toDateString(),
            'harga' => 100000,
            'maks_peserta' => $maks,
            'jenis' => $jenis,
            'status' => $status,
            'players_per_group' => 4,
        ]);
    }

    protected function addCategory(Turnamen $turnamen, string $nama, array $extra = []): TurnamenKategori
    {
        $kat = app(TurnamenKategoriService::class)->create($turnamen, array_merge([
            'nama' => $nama,
            'harga' => 100000,
            'maks_peserta' => 32,
        ], $extra));

        $kat->update(array_merge(['status' => 'open'], $extra));

        return $kat->fresh();
    }

    /**
     * @return array<int, TurnamenPeserta>
     */
    protected function seedApprovedSolos(Turnamen $turnamen, int $kategoriId, int $count): array
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $pemain = Pemain::create([
                'nama' => 'P4_' . $kategoriId . '_' . $i . '_' . random_int(1000, 9999),
                'gender' => $i % 2 === 0 ? 'male' : 'female',
                'no_hp' => '+628' . random_int(1000000000, 1999999999),
                'rating' => 3.0 + ($i % 5) * 0.2,
            ]);

            $rows[] = TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $kategoriId,
                'id_pemain1' => $pemain->id,
                'status' => 'approved',
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]);
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function playerPayloads(int $count, string $tag): array
    {
        $players = [];

        for ($i = 0; $i < $count; $i++) {
            $players[] = [
                'nama' => "Group{$tag}{$i}",
                'gender' => $i % 2 ? 'male' : 'female',
                'no_hp' => '+628' . random_int(2000000000, 2999999999),
                'rating' => 3,
            ];
        }

        return $players;
    }

    protected function scoreAllGroupMatches(Turnamen $turnamen, int $kategoriId, MatchScoringService $scoring): void
    {
        $matches = Pertandingan::query()
            ->where('id_kategori', $kategoriId)
            ->whereNotNull('id_grup')
            ->with(['peserta1.pemain1', 'peserta2.pemain1'])
            ->orderBy('id')
            ->get();

        foreach ($matches as $index => $match) {
            $scoring->recordScore($match, [
                ['skor_pemain1' => 6, 'skor_pemain2' => 2 + ($index % 3)],
                ['skor_pemain1' => 6, 'skor_pemain2' => 1 + ($index % 2)],
            ]);
        }
    }
}
