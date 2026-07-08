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
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a full demo dataset aligned with current app logic:
 * - single / double / mahjong × open / ongoing / completed (9 turnamen)
 * - each turnamen has 20+ pemain
 * - a veteran pool is registered across multiple turnamen
 * - lifecycle uses GroupMatchmaking, KnockoutBracket, Mahjong, Scoring, Completion services
 */
class BornPadelFreshSeeder extends Seeder
{
    /** @var GroupMatchmakingService */
    protected $groupService;

    /** @var MahjongMatchmakingService */
    protected $mahjongService;

    /** @var KnockoutBracketService */
    protected $bracketService;

    /** @var MatchScoringService */
    protected $scoringService;

    /** @var TournamentCompletionService */
    protected $completionService;

    /** Running counter used to generate unique phone numbers. */
    protected $phoneSeq = 0;

    /** @var Pemain[] Shared players that appear in more than one turnamen. */
    protected $veterans = [];

    /** Pointer used to rotate through the veteran pool. */
    protected $veteranCursor = 0;

    /** Players per tournament (approved + fillers where applicable). */
    const PLAYERS_PER_TURNAMEN = 20;

    /** Veterans reused across multiple tournaments. */
    const VETERAN_POOL_SIZE = 12;

    public function run()
    {
        $this->groupService = app(GroupMatchmakingService::class);
        $this->mahjongService = app(MahjongMatchmakingService::class);
        $this->bracketService = app(KnockoutBracketService::class);
        $this->scoringService = app(MatchScoringService::class);
        $this->completionService = app(TournamentCompletionService::class);

        $this->truncateApplicationData();

        $this->veterans = $this->createVeteranPool(self::VETERAN_POOL_SIZE);

        $definitions = [
            'single' => [
                'label' => 'Padel Singles',
                'harga' => 175000,
                'syarat' => 'Turnamen single padel terbuka untuk semua level.',
            ],
            'double' => [
                'label' => 'Padel Doubles',
                'harga' => 300000,
                'syarat' => 'Turnamen double: peserta mendaftar individu, pasangan dibuat otomatis saat pendaftaran ditutup.',
            ],
            'mahjong' => [
                'label' => 'Mahjong',
                'harga' => 200000,
                'syarat' => 'Turnamen Mahjong poin akumulasi. Pemain dibagi ke grup berisi 4.',
            ],
        ];

        $firstOngoing = null;

        foreach ($definitions as $jenis => $meta) {
            $this->seedOpenTurnamen($jenis, $meta);
            $ongoing = $this->seedOngoingTurnamen($jenis, $meta);
            $this->seedCompletedTurnamen($jenis, $meta);

            if ($firstOngoing === null) {
                $firstOngoing = $ongoing;
            }
        }

        $this->assignPanitia($firstOngoing);

        $pemainCount = Pemain::count();
        $multiRegistered = TurnamenPeserta::query()
            ->select('id_pemain1')
            ->groupBy('id_pemain1')
            ->havingRaw('COUNT(DISTINCT id_turnamen) > 1')
            ->count();

        $this->command->info('Fresh seed completed.');
        $this->command->info('  9 turnamen: single/double/mahjong × open/ongoing/completed.');
        $this->command->info("  {$pemainCount} pemain total; {$multiRegistered} registered in more than 1 turnamen.");
        $this->command->info('  Login: admin / panitia — password: 12345678');
    }

    protected function truncateApplicationData(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('m_users')->update(['id_turnamen' => null]);

        $tables = [
            'pertandingan_skor',
            'pertandingan',
            'grup_member',
            'turnamen_pemenang',
            'grup',
            'turnamen_peserta',
            'm_pemain',
            'm_turnamen',
            'personal_access_tokens',
            'password_resets',
            'failed_jobs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->command->warn('Application data truncated (m_users kept).');
    }

    protected function assignPanitia(?Turnamen $turnamen): void
    {
        if (! $turnamen) {
            return;
        }

        User::where('username', 'panitia')->update([
            'id_turnamen' => $turnamen->id,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Open turnamen                                                       */
    /* ------------------------------------------------------------------ */

    protected function seedOpenTurnamen(string $jenis, array $meta): Turnamen
    {
        $turnamen = $this->createTurnamen(
            "Born {$meta['label']} — Pendaftaran Dibuka 2026",
            $jenis,
            '2026-09-12',
            $meta['harga'],
            $meta['syarat'],
            'open'
        );

        // All veterans register here (cross-turnamen coverage).
        foreach ($this->veterans as $index => $veteran) {
            $this->registerPlayer($turnamen, $veteran, $this->rotatingStatus($index));
        }

        $dedicated = self::PLAYERS_PER_TURNAMEN - count($this->veterans);
        if ($dedicated < 8) {
            $dedicated = 8;
        }

        for ($i = 1; $i <= $dedicated; $i++) {
            $pemain = $this->createPemain(
                "{$meta['label']} Open " . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                $i % 2 === 1 ? 'male' : 'female',
                $this->ratingFor($i)
            );

            $this->registerPlayer($turnamen, $pemain, $this->rotatingStatus($i));
        }

        return $turnamen;
    }

    /* ------------------------------------------------------------------ */
    /* Ongoing turnamen                                                    */
    /* ------------------------------------------------------------------ */

    protected function seedOngoingTurnamen(string $jenis, array $meta): Turnamen
    {
        $turnamen = $this->createTurnamen(
            "Born {$meta['label']} — Sedang Berlangsung 2026",
            $jenis,
            '2026-05-18',
            $meta['harga'],
            $meta['syarat'],
            'open'
        );

        $this->registerApprovedRoster($turnamen, $jenis, $meta, 'Ongoing');
        $this->addPendingFillers($turnamen, $meta, 2);

        $this->groupService->closeRegistration($turnamen);
        $turnamen->refresh();

        if ($turnamen->isMahjong()) {
            $this->mahjongService->generateGroups($turnamen, 'random');
            $this->applyMahjongPoints($turnamen);

            return $turnamen;
        }

        $this->groupService->generateRandomGroups($turnamen, 3, 5, 'by_rating');
        $this->playGroupStage($turnamen);

        // Left ongoing: group stage done, knockout not yet generated.
        return $turnamen;
    }

    /* ------------------------------------------------------------------ */
    /* Completed turnamen                                                  */
    /* ------------------------------------------------------------------ */

    protected function seedCompletedTurnamen(string $jenis, array $meta): Turnamen
    {
        $turnamen = $this->createTurnamen(
            "Born {$meta['label']} — Selesai 2025",
            $jenis,
            '2025-12-08',
            $meta['harga'],
            $meta['syarat'],
            'open'
        );

        $this->registerApprovedRoster($turnamen, $jenis, $meta, 'Juara');

        $this->groupService->closeRegistration($turnamen);
        $turnamen->refresh();

        if ($turnamen->isMahjong()) {
            $this->mahjongService->generateGroups($turnamen, 'random');
            $this->applyMahjongPoints($turnamen);

            // 20 players / 5 groups → advance top 4 into final table.
            $this->mahjongService->advanceRound($turnamen, MahjongMatchmakingService::PLAYERS_PER_GROUP);
            $turnamen->refresh();
            $this->applyMahjongPoints($turnamen);

            $this->completionService->complete($turnamen);

            return $turnamen;
        }

        $this->groupService->generateRandomGroups($turnamen, 3, 5, 'by_rating');
        $this->playGroupStage($turnamen);

        $this->bracketService->generateKnockoutBracket($turnamen, 2);
        $this->playKnockoutStage($turnamen);

        $this->completionService->complete($turnamen);

        return $turnamen;
    }

    /* ------------------------------------------------------------------ */
    /* Roster helpers                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Registers an approved roster of PLAYERS_PER_TURNAMEN individuals.
     * A portion comes from the veteran pool so players span multiple turnamen.
     */
    protected function registerApprovedRoster(
        Turnamen $turnamen,
        string $jenis,
        array $meta,
        string $nameTag
    ): void {
        $approvedTarget = self::PLAYERS_PER_TURNAMEN;
        $veteranSlots = min(6, count($this->veterans));
        $dedicated = $approvedTarget - $veteranSlots;

        foreach ($this->drawVeterans($veteranSlots) as $veteran) {
            $this->registerPlayer($turnamen, $veteran, 'approved');
        }

        for ($i = 1; $i <= $dedicated; $i++) {
            $pemain = $this->createPemain(
                "{$meta['label']} {$nameTag} " . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                $i % 2 === 1 ? 'male' : 'female',
                $this->ratingFor($i)
            );

            $this->registerPlayer($turnamen, $pemain, 'approved');
        }
    }

    protected function addPendingFillers(Turnamen $turnamen, array $meta, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $pemain = $this->createPemain(
                "{$meta['label']} Waitlist " . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                $i % 2 === 1 ? 'female' : 'male',
                $this->ratingFor($i + 5)
            );

            $this->registerPlayer($turnamen, $pemain, 'pending');
        }
    }

    protected function registerPlayer(Turnamen $turnamen, Pemain $pemain, string $status): void
    {
        TurnamenPeserta::firstOrCreate(
            [
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
            ],
            [
                'id_pemain2' => null,
                'status' => $status,
                'bukti_bayar' => null,
                'paired_at' => null,
            ]
        );
    }

    /* ------------------------------------------------------------------ */
    /* Match play helpers                                                  */
    /* ------------------------------------------------------------------ */

    protected function playGroupStage(Turnamen $turnamen): void
    {
        $matches = Pertandingan::where('id_turnamen', $turnamen->id)
            ->where('nama_ronde', 'Fase Grup')
            ->where('status', 'scheduled')
            ->orderBy('id')
            ->get();

        foreach ($matches as $index => $match) {
            $fresh = Pertandingan::find($match->id);

            if (! $fresh || ! $fresh->isReadyForScoring() || $fresh->status === 'completed') {
                continue;
            }

            $this->scoringService->recordScore($fresh, $this->setsForSide(($index % 2) + 1));
        }
    }

    protected function playKnockoutStage(Turnamen $turnamen): void
    {
        $rounds = [
            'Babak 16 Besar',
            'Perempatfinal',
            'Semifinal',
            'Final',
            'Perebutan Juara 3',
        ];

        foreach ($rounds as $round) {
            $matches = Pertandingan::where('id_turnamen', $turnamen->id)
                ->whereNull('id_grup')
                ->where('nama_ronde', $round)
                ->where('status', 'scheduled')
                ->orderBy('id')
                ->get();

            foreach ($matches as $match) {
                $fresh = Pertandingan::find($match->id);

                if (! $fresh || $fresh->status === 'completed' || ! $fresh->isReadyForScoring()) {
                    continue;
                }

                // Side 1 wins to keep results deterministic.
                $this->scoringService->recordScore($fresh, $this->setsForSide(1));
            }
        }
    }

    protected function applyMahjongPoints(Turnamen $turnamen): void
    {
        $members = GrupMember::whereHas('grup', function ($query) use ($turnamen) {
            $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
        })->orderBy('id')->get();

        foreach ($members as $index => $member) {
            $this->mahjongService->updateMemberPoints($member, 5 + (($index * 7) % 40));
        }
    }

    /**
     * @return array<int, array{skor_pemain1:int, skor_pemain2:int}>
     */
    protected function setsForSide(int $winnerSide): array
    {
        if ($winnerSide === 1) {
            return [
                ['skor_pemain1' => 6, 'skor_pemain2' => 2],
                ['skor_pemain1' => 6, 'skor_pemain2' => 4],
            ];
        }

        return [
            ['skor_pemain1' => 2, 'skor_pemain2' => 6],
            ['skor_pemain1' => 4, 'skor_pemain2' => 6],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Factory helpers                                                     */
    /* ------------------------------------------------------------------ */

    protected function createTurnamen(
        string $nama,
        string $jenis,
        string $tanggal,
        int $harga,
        string $syarat,
        string $status
    ): Turnamen {
        return Turnamen::create([
            'nama' => $nama,
            'tanggal' => $tanggal,
            'harga' => $harga,
            'syarat' => $syarat,
            'jenis' => $jenis,
            'status' => $status,
            'mahjong_is_final' => false,
            'registration_paired_at' => null,
        ]);
    }

    /**
     * @return Pemain[]
     */
    protected function createVeteranPool(int $count): array
    {
        $pool = [];

        for ($i = 1; $i <= $count; $i++) {
            $pool[] = $this->createPemain(
                'Veteran Pemain ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                $i % 2 === 1 ? 'male' : 'female',
                $this->ratingFor($i + 3)
            );
        }

        return $pool;
    }

    /**
     * @return Pemain[]
     */
    protected function drawVeterans(int $count): array
    {
        $drawn = [];

        for ($i = 0; $i < $count; $i++) {
            $drawn[] = $this->veterans[$this->veteranCursor % count($this->veterans)];
            $this->veteranCursor++;
        }

        return $drawn;
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

        // Stored as country code + local digits (no leading 0), matching PhoneNumberService.
        return '+62' . '8' . str_pad((string) $this->phoneSeq, 9, '0', STR_PAD_LEFT);
    }

    protected function ratingFor(int $index): float
    {
        $base = 2.5 + (($index * 7) % 26) / 10;

        return round(min(5.0, max(2.0, $base)), 2);
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
}
