<?php

namespace Database\Seeders;

use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\MahjongMatchmakingService;
use App\Services\MatchScoringService;
use App\Services\TournamentCompletionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Demo dataset for manually testing current product features.
 *
 * Login:
 *   admin   / 12345678
 *   panitia / 12345678  (assigned to "[ONGOING] Single — Siap Total Lolos")
 *
 * Guest existing-profile phones (local form: 081200000001 / 081200000002):
 *   +6281200000001  Demo Existing Profile A
 *   +6281200000002  Demo Existing Profile B
 *
 * Run:
 *   php artisan db:seed --class=FeatureDemoSeeder
 */
class FeatureDemoSeeder extends Seeder
{
    protected $groupService;
    protected $mahjongService;
    protected $bracketService;
    protected $scoringService;
    protected $completionService;

    protected $phoneSeq = 1000;

    /** @var Pemain|null */
    protected $existingProfileA;

    /** @var Pemain|null */
    protected $existingProfileB;

    /** @var Turnamen|null */
    protected $panitiaTurnamen;

    public function run()
    {
        $this->groupService = app(GroupMatchmakingService::class);
        $this->mahjongService = app(MahjongMatchmakingService::class);
        $this->bracketService = app(KnockoutBracketService::class);
        $this->scoringService = app(MatchScoringService::class);
        $this->completionService = app(TournamentCompletionService::class);

        $this->truncateApplicationData();
        $this->seedUsers();
        $this->seedKnownExistingProfiles();

        $this->seedOpenSingleForShareAndRegister();
        $this->seedOpenDoubleForPairRegister();
        $this->seedOngoingPartialGroupsForGd();
        $this->panitiaTurnamen = $this->seedReadyForTotalLolos();
        $this->seedBracketWithScoresForReset();
        $this->seedFinalDoneJuara3Pending();
        $this->seedCompletedSingleClean();
        $this->seedOngoingMahjong();
        $this->seedDraftTurnamen();

        if ($this->panitiaTurnamen) {
            User::where('username', 'panitia')->update([
                'id_turnamen' => $this->panitiaTurnamen->id,
            ]);
        }

        $this->printSummary();
    }

    protected function truncateApplicationData(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('m_users')) {
            DB::table('m_users')->update(['id_turnamen' => null]);
        }

        foreach ([
            'pertandingan_skor',
            'pertandingan',
            'grup_member',
            'turnamen_pemenang',
            'turnamen_pasangan',
            'grup',
            'turnamen_peserta',
            'm_pemain',
            'm_turnamen',
            'personal_access_tokens',
            'password_resets',
            'failed_jobs',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
        $this->command->warn('Application data truncated.');
    }

    protected function seedUsers(): void
    {
        $password = Hash::make('12345678');

        foreach ([
            [
                'name' => 'Admin Born Padel',
                'username' => 'admin',
                'email' => 'admin@bornpadel.com',
                'role' => 'admin',
            ],
            [
                'name' => 'Panitia Born Padel',
                'username' => 'panitia',
                'email' => 'panitia@bornpadel.com',
                'role' => 'panitia',
            ],
        ] as $account) {
            User::updateOrCreate(
                ['username' => $account['username']],
                array_merge($account, [
                    'password' => $password,
                    'id_turnamen' => null,
                ])
            );
        }
    }

    protected function seedKnownExistingProfiles(): void
    {
        $this->existingProfileA = Pemain::create([
            'nama' => 'Demo Existing Profile A',
            'tgl_lahir' => '1995-03-15',
            'usia' => Carbon::parse('1995-03-15')->age,
            'gender' => 'male',
            'no_hp' => '+6281200000001',
            'rating' => 3.8,
            'total_poin' => 20,
        ]);

        $this->existingProfileB = Pemain::create([
            'nama' => 'Demo Existing Profile B',
            'tgl_lahir' => '1998-07-22',
            'usia' => Carbon::parse('1998-07-22')->age,
            'gender' => 'female',
            'no_hp' => '+6281200000002',
            'rating' => 3.2,
            'total_poin' => 10,
        ]);
    }

    /** Guest landing share + register + existing HP confirm. */
    protected function seedOpenSingleForShareAndRegister(): void
    {
        $turnamen = $this->createTurnamen(
            '[OPEN] Single — Share & Register',
            'single',
            now()->addDays(21)->toDateString(),
            175000,
            "Gunakan untuk uji:\n- Tombol Bagikan di landing\n- Daftar guest (HP baru)\n- Konfirmasi profil existing (HP 081200000001)",
            'open',
            32
        );

        $this->registerPlayer($turnamen, $this->existingProfileA, 'approved');
        $this->registerPlayer($turnamen, $this->existingProfileB, 'pending');

        for ($i = 1; $i <= 10; $i++) {
            $this->registerPlayer(
                $turnamen,
                $this->createPemain("Open Single {$i}", $i % 2 ? 'male' : 'female', $this->ratingFor($i)),
                $this->rotatingStatus($i)
            );
        }
    }

    /** Guest pair registration + harga x2. */
    protected function seedOpenDoubleForPairRegister(): void
    {
        $turnamen = $this->createTurnamen(
            '[OPEN] Double — Pair Register',
            'double',
            now()->addDays(28)->toDateString(),
            300000,
            'Uji pendaftaran berpasangan (harga tampil 2x).',
            'open',
            24
        );

        for ($i = 1; $i <= 8; $i++) {
            $this->registerPlayer(
                $turnamen,
                $this->createPemain("Open Double {$i}", $i % 2 ? 'female' : 'male', $this->ratingFor($i + 2)),
                $i <= 6 ? 'approved' : 'pending'
            );
        }
    }

    /** Partial group scores → GD standings. */
    protected function seedOngoingPartialGroupsForGd(): void
    {
        $turnamen = $this->createTurnamen(
            '[ONGOING] Single — Grup Partial (GD)',
            'single',
            now()->subDays(2)->toDateString(),
            150000,
            'Uji klasemen GD (selisih game) dengan skor grup parsial + edit skor.',
            'open',
            16
        );

        $this->registerApprovedCount($turnamen, 12, 'GD Partial');
        $this->groupService->closeRegistration($turnamen);
        $turnamen->refresh();

        $this->groupService->generateRandomGroups($turnamen, 3, 4, 'by_rating');
        $this->groupService->generateGroupMatches($turnamen);
        $this->playGroupStage($turnamen, 0.55);
    }

    /** 20 players / 5 groups of 4 / all group matches done → total lolos 16. */
    protected function seedReadyForTotalLolos(): Turnamen
    {
        $turnamen = $this->createTurnamen(
            '[ONGOING] Single — Siap Total Lolos',
            'single',
            now()->subDay()->toDateString(),
            175000,
            "Uji End Group Stage mode Total lolos:\n20 pemain, 5 grup × 4, semua skor grup selesai.\nContoh: 16 lolos = top 3 tiap grup + 1 best 4th.",
            'open',
            20
        );

        $this->registerApprovedCount($turnamen, 20, 'Total Lolos');
        $this->groupService->closeRegistration($turnamen);
        $turnamen->refresh();

        $this->groupService->generateRandomGroups($turnamen, 4, 4, 'by_rating');
        $this->groupService->generateGroupMatches($turnamen);
        $this->playGroupStage($turnamen, 1.0);

        return $turnamen->fresh();
    }

    /** Bracket exists with some scores → Reset Bracket + password. */
    protected function seedBracketWithScoresForReset(): void
    {
        $turnamen = $this->createTurnamen(
            '[ONGOING] Single — Bracket + Skor (Reset)',
            'single',
            now()->subDays(3)->toDateString(),
            175000,
            'Uji Reset Bracket (ada skor → minta password). Klasemen grup tetap.',
            'open',
            16
        );

        $this->registerApprovedCount($turnamen, 8, 'Reset KO');
        $this->groupService->closeRegistration($turnamen);
        $turnamen->refresh();

        $this->groupService->generateRandomGroups($turnamen, 2, 2, 'random');
        $this->groupService->generateGroupMatches($turnamen);
        $this->playGroupStage($turnamen, 1.0);

        $this->bracketService->generateKnockoutBracket($turnamen->fresh(), 1);

        $firstSf = Pertandingan::query()
            ->where('id_turnamen', $turnamen->id)
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Semifinal')
            ->orderBy('id')
            ->first();

        if ($firstSf) {
            $this->recordVariedScore($firstSf, 0, Carbon::parse($turnamen->tanggal)->setTime(15, 0));
        }
    }

    /** Final done, Juara 3 pending → Selesaikan Turnamen tanpa J3. */
    protected function seedFinalDoneJuara3Pending(): void
    {
        $turnamen = $this->createTurnamen(
            '[ONGOING] Single — Final Done J3 Pending',
            'single',
            now()->subDays(4)->toDateString(),
            175000,
            'Uji Selesaikan Turnamen setelah Final (Juara 3 belum dimainkan → konfirmasi + cancel).',
            'open',
            16
        );

        $this->registerApprovedCount($turnamen, 8, 'Final Ready');
        $this->groupService->closeRegistration($turnamen);
        $turnamen->refresh();

        $this->groupService->generateRandomGroups($turnamen, 2, 2, 'random');
        $this->groupService->generateGroupMatches($turnamen);
        $this->playGroupStage($turnamen, 1.0);

        $this->bracketService->generateKnockoutBracket($turnamen->fresh(), 1);
        $this->playKnockoutRounds($turnamen, ['Semifinal', 'Final']);
    }

    protected function seedCompletedSingleClean(): void
    {
        $turnamen = $this->createTurnamen(
            '[DONE] Single — Completed',
            'single',
            now()->subMonths(2)->toDateString(),
            150000,
            'Uji landing Turnamen Selesai + klasemen/bracket hasil akhir.',
            'open',
            16
        );

        $this->registerApprovedCount($turnamen, 8, 'Champion');
        $this->groupService->closeRegistration($turnamen);
        $turnamen->refresh();

        $this->groupService->generateRandomGroups($turnamen, 2, 2, 'by_rating');
        $this->groupService->generateGroupMatches($turnamen);
        $this->playGroupStage($turnamen, 1.0);

        $this->bracketService->generateKnockoutBracket($turnamen->fresh(), 1);
        $this->playKnockoutRounds($turnamen, ['Semifinal', 'Final', 'Perebutan Juara 3']);
        $this->completionService->complete($turnamen->fresh());
    }

    protected function seedOngoingMahjong(): void
    {
        $turnamen = $this->createTurnamen(
            '[ONGOING] Mahjong — Babak',
            'mahjong',
            now()->subDays(1)->toDateString(),
            200000,
            'Uji matchmaking mahjong + input poin babak.',
            'open',
            16
        );

        $this->registerApprovedCount($turnamen, 16, 'Mahjong');
        $this->groupService->closeRegistration($turnamen);
        $turnamen->refresh();

        $this->mahjongService->generateGroups($turnamen, 'random');
        $this->applyMahjongPoints($turnamen);
    }

    protected function seedDraftTurnamen(): void
    {
        $this->createTurnamen(
            '[DRAFT] Single — Manajemen Turnamen',
            'single',
            now()->addMonths(2)->toDateString(),
            100000,
            'Uji Manajemen Turnamen (edit/hapus/kelola) — status draft.',
            'draft',
            16
        );
    }

    /* ------------------------------------------------------------------ */

    protected function printSummary(): void
    {
        $this->command->info('FeatureDemoSeeder completed.');
        $this->command->info('Login: admin / panitia — password: 12345678');
        $this->command->info('Guest existing HP: 081200000001 / 081200000002');
        $this->command->info('Panitia assigned to: [ONGOING] Single — Siap Total Lolos');
        $this->command->table(
            ['Turnamen scenario', 'Purpose'],
            [
                ['[OPEN] Single — Share & Register', 'Bagikan + daftar + confirm existing HP'],
                ['[OPEN] Double — Pair Register', 'Daftar berpasangan / harga x2'],
                ['[ONGOING] Single — Grup Partial (GD)', 'Klasemen GD + edit skor grup'],
                ['[ONGOING] Single — Siap Total Lolos', 'End Group Total 16/20 + panitia'],
                ['[ONGOING] Single — Bracket + Skor (Reset)', 'Reset Bracket + password'],
                ['[ONGOING] Single — Final Done J3 Pending', 'Selesaikan tanpa Juara 3'],
                ['[DONE] Single — Completed', 'Landing selesai + hasil'],
                ['[ONGOING] Mahjong — Babak', 'Mahjong groups/points'],
                ['[DRAFT] Single — Manajemen Turnamen', 'CRUD turnamen admin'],
            ]
        );
    }

    protected function createTurnamen(
        string $nama,
        string $jenis,
        string $tanggal,
        int $harga,
        string $syarat,
        string $status,
        ?int $maksPeserta = null
    ): Turnamen {
        return Turnamen::create([
            'nama' => $nama,
            'tanggal' => $tanggal,
            'harga' => $harga,
            'syarat' => $syarat,
            'jenis' => $jenis,
            'status' => $status,
            'maks_peserta' => $maksPeserta,
            'mahjong_is_final' => false,
            'registration_paired_at' => null,
        ]);
    }

    protected function registerApprovedCount(Turnamen $turnamen, int $count, string $prefix): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->registerPlayer(
                $turnamen,
                $this->createPemain(
                    "{$prefix} " . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    $i % 2 ? 'male' : 'female',
                    $this->ratingFor($i)
                ),
                'approved'
            );
        }
    }

    protected function registerPlayer(
        Turnamen $turnamen,
        Pemain $pemain,
        string $status,
        string $sumber = TurnamenPeserta::SUMBER_INTERNAL
    ): void {
        TurnamenPeserta::firstOrCreate(
            [
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
            ],
            [
                'status' => $status,
                'sumber' => $sumber,
                'bukti_bayar' => null,
            ]
        );
    }

    protected function createPemain(string $nama, string $gender, float $rating): Pemain
    {
        $noHp = $this->nextPhone();
        $birthYear = 1988 + (crc32($noHp) % 15);
        $tglLahir = Carbon::create($birthYear, (crc32($nama) % 12) + 1, (crc32($nama) % 27) + 1);

        return Pemain::create([
            'nama' => $nama,
            'tgl_lahir' => $tglLahir,
            'usia' => $tglLahir->age,
            'gender' => $gender,
            'no_hp' => $noHp,
            'rating' => $rating,
            'total_poin' => 0,
        ]);
    }

    protected function nextPhone(): string
    {
        $this->phoneSeq++;

        return '+62' . '8' . str_pad((string) $this->phoneSeq, 9, '0', STR_PAD_LEFT);
    }

    protected function ratingFor(int $index): float
    {
        return round(min(5.0, max(2.0, 2.5 + (($index * 7) % 26) / 10)), 2);
    }

    protected function rotatingStatus(int $index): string
    {
        $mod = $index % 10;

        if ($mod === 0) {
            return 'paid';
        }
        if ($mod === 1) {
            return 'pending';
        }
        if ($mod === 2) {
            return 'unpaid';
        }

        return 'approved';
    }

    protected function playGroupStage(Turnamen $turnamen, float $completionRatio = 1.0): void
    {
        $matches = Pertandingan::where('id_turnamen', $turnamen->id)
            ->where('nama_ronde', 'Fase Grup')
            ->where('status', 'scheduled')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        $playCount = $completionRatio >= 1.0
            ? $matches->count()
            : max(1, (int) floor($matches->count() * $completionRatio));

        $baseTime = Carbon::parse($turnamen->tanggal)->setTime(9, 0);

        foreach ($matches->take($playCount) as $sequence => $match) {
            $this->recordVariedScore($match, $sequence, $baseTime);
        }
    }

    /**
     * @param  list<string>  $rounds
     */
    protected function playKnockoutRounds(Turnamen $turnamen, array $rounds): void
    {
        $sequence = 0;
        $baseTime = Carbon::parse($turnamen->tanggal)->setTime(14, 0);

        foreach ($rounds as $round) {
            $matches = Pertandingan::where('id_turnamen', $turnamen->id)
                ->whereNull('id_grup')
                ->where('nama_ronde', $round)
                ->where('status', 'scheduled')
                ->orderBy('id')
                ->get();

            foreach ($matches as $match) {
                $this->recordVariedScore($match, $sequence++, $baseTime);
            }
        }
    }

    protected function recordVariedScore(Pertandingan $match, int $sequence, Carbon $baseTime): void
    {
        $fresh = Pertandingan::find($match->id);

        if (! $fresh || ! $fresh->isReadyForScoring() || $fresh->status === 'completed') {
            return;
        }

        $winnerSide = $this->resolveWinnerSide($fresh);
        $patternIndex = ($fresh->id + ($sequence * 5)) % count($this->scorePatterns());
        $playedAt = (clone $baseTime)->addMinutes($sequence * 23 + ($fresh->id % 11));

        Carbon::setTestNow($playedAt);

        try {
            $this->scoringService->recordScore($fresh, $this->buildSets($winnerSide, $patternIndex));
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function resolveWinnerSide(Pertandingan $match): int
    {
        $rating1 = (float) optional(Pemain::find($match->id_pemain1))->rating;
        $rating2 = (float) optional(Pemain::find($match->id_pemain2))->rating;

        if ($rating1 !== $rating2 && abs($rating1 - $rating2) >= 0.25) {
            return $rating1 > $rating2 ? 1 : 2;
        }

        $seed = (int) $match->id_pemain1 + (int) $match->id_pemain2 + (int) $match->id;

        return ($seed % 2) === 0 ? 1 : 2;
    }

    protected function scorePatterns(): array
    {
        return [
            [['6', '2'], ['6', '3']],
            [['6', '4'], ['6', '4']],
            [['6', '1'], ['6', '2']],
            [['7', '5'], ['6', '3']],
            [['6', '3'], ['6', '0']],
            [['6', '4'], ['4', '6'], ['6', '2']],
            [['6', '3'], ['3', '6'], ['6', '4']],
            [['7', '5'], ['6', '7'], ['6', '3']],
        ];
    }

    protected function buildSets(int $winnerSide, int $patternIndex): array
    {
        $patterns = $this->scorePatterns();
        $pattern = $patterns[$patternIndex % count($patterns)];
        $sets = [];

        foreach ($pattern as $games) {
            [$winnerGames, $loserGames] = $games;

            $sets[] = $winnerSide === 1
                ? ['skor_pemain1' => (int) $winnerGames, 'skor_pemain2' => (int) $loserGames]
                : ['skor_pemain1' => (int) $loserGames, 'skor_pemain2' => (int) $winnerGames];
        }

        return $sets;
    }

    protected function applyMahjongPoints(Turnamen $turnamen): void
    {
        $members = GrupMember::whereHas('grup', function ($query) use ($turnamen) {
            $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
        })->orderBy('id')->get();

        foreach ($members as $index => $member) {
            $babak = (int) optional($member->grup)->babak;
            $base = 8 + (($index * 11 + $babak * 5) % 35);
            $swing = (($member->id * 3 + $index) % 7) - 3;
            $this->mahjongService->updateMemberPoints($member, $base + $swing);
        }
    }
}
