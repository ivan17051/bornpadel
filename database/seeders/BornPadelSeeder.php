<?php

namespace Database\Seeders;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use App\Services\DoublePairingService;
use App\Services\FriendlyMatchmakingService;
use App\Services\GroupMatchmakingService;
use App\Services\MahjongMatchmakingService;
use App\Services\MahjongTeamMatchmakingService;
use App\Services\PemainRegistrationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical demo dataset for the current app.
 *
 * Login (password: 12345678):
 *   admin    — full access
 *   panitia  — assigned to all ongoing seeded tournaments
 *
 * Run:
 *   php artisan db:seed
 */
class BornPadelSeeder extends Seeder
{
    protected $phoneSeq = 0;

    /** @var array<int, Turnamen> */
    protected $ongoingTurnamen = [];

    public function run()
    {
        $groupService = app(GroupMatchmakingService::class);
        $pairingService = app(DoublePairingService::class);
        $mahjongService = app(MahjongMatchmakingService::class);
        $mahjongTeamService = app(MahjongTeamMatchmakingService::class);
        $friendlyService = app(FriendlyMatchmakingService::class);
        $registrationService = app(PemainRegistrationService::class);

        $this->truncateApplicationData();
        $this->seedUsers();

        $this->seedSingle($groupService);
        $this->seedDouble($groupService, $pairingService);
        $this->seedMahjong($groupService, $mahjongService);
        $this->seedMahjongTeam($groupService, $mahjongTeamService, $registrationService);
        $this->seedFriendly($groupService, $friendlyService, $registrationService);

        $this->assignPanitia();

        if ($this->command) {
            $this->command->info('BornPadelSeeder selesai.');
            $this->command->info('  Jenis: single, double, mahjong, mahjong_team, friendly × open + ongoing.');
            $this->command->info('  Login: admin / panitia — password: 12345678');
        }
    }

    protected function seedSingle(GroupMatchmakingService $groupService): void
    {
        $open = $this->createTurnamen(
            'Born Padel Singles — Pendaftaran Dibuka',
            'single',
            175000,
            '16 pemain approved (genap) — siap tutup pendaftaran.',
            16
        );
        $this->registerIndividuals($open, 16, 'Single Open');

        $ongoing = $this->createTurnamen(
            'Born Padel Singles — Berlangsung',
            'single',
            175000,
            'Single ongoing: grup dan pertandingan fase grup.',
            16
        );
        $this->registerIndividuals($ongoing, 16, 'Single Ongoing');
        $this->startGroupStage($groupService, $ongoing);
    }

    protected function seedDouble(
        GroupMatchmakingService $groupService,
        DoublePairingService $pairingService
    ): void {
        $open = $this->createTurnamen(
            'Born Padel Doubles — Pendaftaran Dibuka',
            'double',
            300000,
            '8 pasangan lengkap approved — siap tutup pendaftaran.',
            16
        );
        $this->registerPairs($pairingService, $open, 8, 'Double Open');

        $ongoing = $this->createTurnamen(
            'Born Padel Doubles — Berlangsung',
            'double',
            300000,
            'Double ongoing: pasangan lengkap, grup dan pertandingan fase grup.',
            16
        );
        $this->registerPairs($pairingService, $ongoing, 8, 'Double Ongoing');
        $this->startGroupStage($groupService, $ongoing);
    }

    protected function seedMahjong(
        GroupMatchmakingService $groupService,
        MahjongMatchmakingService $mahjongService
    ): void {
        $open = $this->createTurnamen(
            'Born Mahjong — Pendaftaran Dibuka',
            'mahjong',
            200000,
            '16 pemain approved (kelipatan 4) — siap tutup pendaftaran.',
            16
        );
        $this->registerIndividuals($open, 16, 'Mahjong Open');

        $ongoing = $this->createTurnamen(
            'Born Mahjong — Berlangsung',
            'mahjong',
            200000,
            'Mahjong ongoing: 4 grup aktif siap input poin.',
            16
        );
        $this->registerIndividuals($ongoing, 16, 'Mahjong Ongoing');
        $groupService->closeRegistration($ongoing);
        $ongoing->refresh();
        $mahjongService->generateGroups($ongoing, 'random');
        $this->ongoingTurnamen[] = $ongoing->fresh();
    }

    protected function seedMahjongTeam(
        GroupMatchmakingService $groupService,
        MahjongTeamMatchmakingService $mahjongTeamService,
        PemainRegistrationService $registrationService
    ): void {
        $open = $this->createTurnamen(
            'Born Mahjong Tim — Pendaftaran Dibuka',
            'mahjong_team',
            75000,
            '4 tim × 4 pemain approved — siap tutup pendaftaran.',
            16,
            Turnamen::MAHJONG_TEAM_PLAYERS_PER_TEAM
        );
        $this->registerNamedRosters($registrationService, $open, [
            'Dragon Squad',
            'Tiger Clan',
            'Phoenix Tiles',
            'Shadow Tile',
        ], 'MahjongTim Open');

        $ongoing = $this->createTurnamen(
            'Born Mahjong Tim — Berlangsung',
            'mahjong_team',
            75000,
            'Mahjong Tim ongoing: 4 tim + meja silang siap input poin.',
            16,
            Turnamen::MAHJONG_TEAM_PLAYERS_PER_TEAM
        );
        $this->registerNamedRosters($registrationService, $ongoing, [
            'East Wind',
            'West Wind',
            'South Wind',
            'North Wind',
        ], 'MahjongTim Ongoing');
        $groupService->closeRegistration($ongoing);
        $ongoing->refresh();
        $mahjongTeamService->generateTeams($ongoing, 'random');
        $this->ongoingTurnamen[] = $ongoing->fresh();
    }

    protected function seedFriendly(
        GroupMatchmakingService $groupService,
        FriendlyMatchmakingService $friendlyService,
        PemainRegistrationService $registrationService
    ): void {
        $open = $this->createTurnamen(
            'Born Group Match — Pendaftaran Dibuka',
            'friendly',
            200000,
            '4 grup × 4 pemain approved — siap tutup pendaftaran.',
            16,
            Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP
        );
        $this->registerNamedRosters($registrationService, $open, [
            'Alpha Wolves',
            'Night Owls',
            'Smash Squad',
            'Court Kings',
        ], 'Friendly Open');

        $ongoing = $this->createTurnamen(
            'Born Group Match — Berlangsung',
            'friendly',
            200000,
            'Group Match ongoing: grup penuh + slot pertandingan antar grup.',
            16,
            Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP
        );
        $this->registerNamedRosters($registrationService, $ongoing, [
            'Net Ninjas',
            'Baseline Bandits',
            'Drop Shot',
            'Lob Lords',
        ], 'Friendly Ongoing');
        $groupService->closeRegistration($ongoing);
        $ongoing->refresh();
        $friendlyService->generateGroups($ongoing, 'random');
        $this->ongoingTurnamen[] = $ongoing->fresh();
    }

    protected function startGroupStage(GroupMatchmakingService $groupService, Turnamen $turnamen): void
    {
        $groupService->closeRegistration($turnamen);
        $turnamen->refresh();
        $groupService->generateRandomGroups($turnamen, 3, 4, 'random');
        $groupService->generateGroupMatches($turnamen);
        $this->ongoingTurnamen[] = $turnamen->fresh();
    }

    protected function createTurnamen(
        string $nama,
        string $jenis,
        int $harga,
        string $syarat,
        int $maksPeserta,
        ?int $playersPerGroup = null
    ): Turnamen {
        $payload = [
            'nama' => $nama,
            'tanggal' => Carbon::now()->addDays(7)->toDateString(),
            'harga' => $harga,
            'syarat' => $syarat,
            'jenis' => $jenis,
            'status' => 'open',
            'maks_peserta' => $maksPeserta,
            'mahjong_is_final' => false,
            'registration_paired_at' => null,
            'group_matches_generated_at' => null,
        ];

        if ($jenis === 'friendly') {
            $payload['players_per_group'] = $playersPerGroup
                ?? Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP;
        } elseif ($jenis === 'mahjong_team') {
            $payload['players_per_group'] = $playersPerGroup
                ?? Turnamen::MAHJONG_TEAM_PLAYERS_PER_TEAM;
        }

        return Turnamen::create($payload);
    }

    protected function registerIndividuals(Turnamen $turnamen, int $count, string $prefix): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $pemain = $this->createPemain(
                $prefix.' '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                $i % 2 ? 'male' : 'female',
                2.0 + ($i * 0.05)
            );
            $this->registerPlayer($turnamen, $pemain);
        }
    }

    protected function registerPairs(
        DoublePairingService $pairingService,
        Turnamen $turnamen,
        int $pairCount,
        string $prefix
    ): void {
        for ($i = 1; $i <= $pairCount; $i++) {
            $a = $this->createPemain(
                $prefix.' A'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'male',
                2.0 + ($i * 0.05)
            );
            $b = $this->createPemain(
                $prefix.' B'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'female',
                2.1 + ($i * 0.05)
            );

            $pairingService->createPair(
                $turnamen,
                $this->registerPlayer($turnamen, $a),
                $this->registerPlayer($turnamen, $b)
            );
        }
    }

    /**
     * @param  list<string>  $names
     */
    protected function registerNamedRosters(
        PemainRegistrationService $registrationService,
        Turnamen $turnamen,
        array $names,
        string $prefix
    ): void {
        $size = $turnamen->registrationRosterSize();

        foreach ($names as $index => $namaGrup) {
            $players = [];
            for ($i = 1; $i <= $size; $i++) {
                $players[] = [
                    'nama' => $prefix.' '.($index + 1).'-'.$i,
                    'gender' => $i % 2 ? 'male' : 'female',
                    'no_hp' => $this->nextPhone(),
                    'rating' => round(2.0 + (($index * $size) + $i) * 0.05, 2),
                ];
            }

            $registrationService->registerGroup(
                $turnamen,
                $namaGrup,
                $players,
                [],
                null,
                TurnamenPeserta::SUMBER_INTERNAL,
                false,
                'approved'
            );
        }
    }

    protected function registerPlayer(Turnamen $turnamen, Pemain $pemain): TurnamenPeserta
    {
        return TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => 'approved',
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            'bukti_bayar' => null,
        ]);
    }

    protected function createPemain(string $nama, string $gender, float $rating): Pemain
    {
        $noHp = $this->nextPhone();
        $birthYear = 1990 + (crc32($noHp) % 12);
        $tglLahir = Carbon::create($birthYear, (crc32($nama) % 12) + 1, (crc32($nama) % 27) + 1);

        return Pemain::create([
            'nama' => $nama,
            'tgl_lahir' => $tglLahir,
            'usia' => $tglLahir->age,
            'gender' => $gender,
            'no_hp' => $noHp,
            'rating' => round($rating, 2),
            'total_poin' => 0,
        ]);
    }

    protected function nextPhone(): string
    {
        $this->phoneSeq++;

        return '+6281800'.str_pad((string) $this->phoneSeq, 6, '0', STR_PAD_LEFT);
    }

    protected function seedUsers(): void
    {
        $password = Hash::make('12345678');

        User::create([
            'name' => 'Admin Born Padel',
            'username' => 'admin',
            'email' => 'admin@bornpadel.com',
            'password' => $password,
            'role' => 'admin',
            'id_turnamen' => null,
        ]);

        User::create([
            'name' => 'Panitia Born Padel',
            'username' => 'panitia',
            'email' => 'panitia@bornpadel.com',
            'password' => $password,
            'role' => 'panitia',
            'id_turnamen' => null,
        ]);
    }

    protected function assignPanitia(): void
    {
        $panitia = User::query()->where('username', 'panitia')->first();

        if (! $panitia) {
            return;
        }

        $ids = collect($this->ongoingTurnamen)
            ->map(fn (Turnamen $turnamen) => (int) $turnamen->id)
            ->unique()
            ->values()
            ->all();

        $panitia->syncAssignedTurnamen($ids);
    }

    protected function truncateApplicationData(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('m_users') && Schema::hasColumn('m_users', 'id_turnamen')) {
            DB::table('m_users')->update(['id_turnamen' => null]);
        }

        foreach ([
            'pertandingan_skor',
            'mahjong_poin_entry',
            'turnamen_meja_seat',
            'turnamen_meja',
            'pertandingan',
            'grup_member',
            'turnamen_pemenang',
            'turnamen_pasangan',
            'turnamen_grup_pendaftaran_member',
            'turnamen_grup_pendaftaran',
            'grup',
            'turnamen_peserta',
            'user_turnamen',
            'turnamen_kategori',
            'm_pemain',
            'm_turnamen',
            'm_users',
            'personal_access_tokens',
            'password_resets',
            'failed_jobs',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        if ($this->command) {
            $this->command->warn('Application data truncated.');
        }
    }
}
