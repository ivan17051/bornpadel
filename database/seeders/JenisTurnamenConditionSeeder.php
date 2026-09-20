<?php

namespace Database\Seeders;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\DoublePairingService;
use App\Services\FriendlyMatchmakingService;
use App\Services\GroupMatchmakingService;
use App\Services\MahjongMatchmakingService;
use App\Services\MahjongTeamMatchmakingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one open + one ongoing turnamen per jenis with valid roster conditions.
 *
 * Conditions covered:
 * - single: even approved solos (16) — siap tutup pendaftaran / random pair
 * - double: 8 pasangan lengkap approved — siap tutup tanpa solo
 * - mahjong: 16 approved (kelipatan 4)
 * - mahjong_team: 16 approved (4 tim × 4)
 * - friendly: 16 approved, players_per_group = 4 (4 grup)
 *
 * Run:
 *   php artisan db:seed --class=JenisTurnamenConditionSeeder
 *
 * Additive (does not truncate existing data). Uses phone prefix +6281999…
 */
class JenisTurnamenConditionSeeder extends Seeder
{
    protected $phoneSeq = 0;

    /** @var GroupMatchmakingService */
    protected $groupService;

    /** @var DoublePairingService */
    protected $pairingService;

    /** @var MahjongMatchmakingService */
    protected $mahjongService;

    /** @var MahjongTeamMatchmakingService */
    protected $mahjongTeamService;

    /** @var FriendlyMatchmakingService */
    protected $friendlyService;

    public function run()
    {
        $this->groupService = app(GroupMatchmakingService::class);
        $this->pairingService = app(DoublePairingService::class);
        $this->mahjongService = app(MahjongMatchmakingService::class);
        $this->mahjongTeamService = app(MahjongTeamMatchmakingService::class);
        $this->friendlyService = app(FriendlyMatchmakingService::class);

        $this->ensureAdminUsers();

        $created = [];

        $created[] = $this->seedSingle();
        $created[] = $this->seedDouble();
        $created[] = $this->seedMahjong();
        $created[] = $this->seedMahjongTeam();
        $created[] = $this->seedFriendly();

        if ($this->command) {
            $this->command->info('JenisTurnamenConditionSeeder selesai.');
            foreach ($created as $row) {
                $extra = isset($row['open_8'])
                    ? sprintf(' | open8#%d (32)', $row['open_8']->id)
                    : '';
                $this->command->info(sprintf(
                    '  [%s] open#%d (%d approved)%s | ongoing#%d',
                    $row['jenis'],
                    $row['open']->id,
                    $row['approved'],
                    $extra,
                    $row['ongoing']->id
                ));
            }
            $this->command->info('Login: admin / panitia — password: 12345678');
        }
    }

    protected function seedSingle(): array
    {
        $approved = 16;

        $open = $this->createTurnamen(
            '[SEED] Single — Siap Tutup Pendaftaran',
            'single',
            'open',
            150000,
            '16 pemain approved (genap) — siap Tutup Pendaftaran.',
            $approved
        );
        $this->registerApprovedIndividuals($open, $approved, 'Single Open');

        $ongoing = $this->createTurnamen(
            '[SEED] Single — Ongoing Matchmaking',
            'single',
            'open',
            150000,
            'Single ongoing: pendaftaran ditutup, grup + match fase grup.',
            $approved
        );
        $this->registerApprovedIndividuals($ongoing, $approved, 'Single Ongoing');
        $this->groupService->closeRegistration($ongoing);
        $ongoing->refresh();
        $this->groupService->generateRandomGroups($ongoing, 3, 4, 'random');
        $this->groupService->generateGroupMatches($ongoing);

        return ['jenis' => 'single', 'open' => $open, 'ongoing' => $ongoing, 'approved' => $approved];
    }

    protected function seedDouble(): array
    {
        $pairCount = 8;
        $approvedPlayers = $pairCount * 2;

        $open = $this->createTurnamen(
            '[SEED] Double — Siap Tutup Pendaftaran',
            'double',
            'open',
            250000,
            '8 pasangan lengkap approved — siap Tutup Pendaftaran.',
            $approvedPlayers
        );
        $this->registerApprovedPairs($open, $pairCount, 'Double Open');

        $ongoing = $this->createTurnamen(
            '[SEED] Double — Ongoing Matchmaking',
            'double',
            'open',
            250000,
            'Double ongoing: pasangan lengkap, grup + match fase grup.',
            $approvedPlayers
        );
        $this->registerApprovedPairs($ongoing, $pairCount, 'Double Ongoing');
        $this->groupService->closeRegistration($ongoing);
        $ongoing->refresh();
        $this->groupService->generateRandomGroups($ongoing, 3, 4, 'random');
        $this->groupService->generateGroupMatches($ongoing);

        return ['jenis' => 'double', 'open' => $open, 'ongoing' => $ongoing, 'approved' => $approvedPlayers];
    }

    protected function seedMahjong(): array
    {
        $approved = 16;

        $open = $this->createTurnamen(
            '[SEED] Mahjong — Siap Tutup Pendaftaran',
            'mahjong',
            'open',
            50000,
            '16 pemain approved (kelipatan 4) — siap Tutup Pendaftaran.',
            $approved
        );
        $this->registerApprovedIndividuals($open, $approved, 'Mahjong Open');

        $ongoing = $this->createTurnamen(
            '[SEED] Mahjong — Ongoing Grup',
            'mahjong',
            'open',
            50000,
            'Mahjong ongoing: 4 grup aktif siap input poin / reshuffle / lanjut babak.',
            $approved
        );
        $this->registerApprovedIndividuals($ongoing, $approved, 'Mahjong Ongoing');
        $this->groupService->closeRegistration($ongoing);
        $ongoing->refresh();
        $this->mahjongService->generateGroups($ongoing, 'random');

        return ['jenis' => 'mahjong', 'open' => $open, 'ongoing' => $ongoing, 'approved' => $approved];
    }

    protected function seedMahjongTeam(): array
    {
        $approved = 16; // 4 tim × 4
        $approvedEightTeams = 32; // 8 tim × 4

        $open = $this->createTurnamen(
            '[SEED] Mahjong Tim — Siap Tutup (4 Tim)',
            'mahjong_team',
            'open',
            75000,
            '16 pemain approved (4 tim) — syarat Tutup Pendaftaran Mahjong Tim.',
            $approved
        );
        $this->registerApprovedIndividuals($open, $approved, 'MahjongTim Open4');

        $openEight = $this->createTurnamen(
            '[SEED] Mahjong Tim — Siap Tutup (8 Tim)',
            'mahjong_team',
            'open',
            75000,
            '32 pemain approved (8 tim) — syarat Tutup Pendaftaran Mahjong Tim.',
            $approvedEightTeams
        );
        $this->registerApprovedIndividuals($openEight, $approvedEightTeams, 'MahjongTim Open8');

        $ongoing = $this->createTurnamen(
            '[SEED] Mahjong Tim — Ongoing Meja',
            'mahjong_team',
            'open',
            75000,
            'Mahjong Tim ongoing: 4 tim + meja silang siap input poin / reshuffle / akhiri babak.',
            $approved
        );
        $this->registerApprovedIndividuals($ongoing, $approved, 'MahjongTim Ongoing');
        $this->groupService->closeRegistration($ongoing);
        $ongoing->refresh();
        $this->mahjongTeamService->generateTeams($ongoing, 'random');

        return [
            'jenis' => 'mahjong_team',
            'open' => $open,
            'open_8' => $openEight,
            'ongoing' => $ongoing,
            'approved' => $approved,
        ];
    }

    protected function seedFriendly(): array
    {
        $playersPerGroup = 4;
        $approved = 16; // 4 grup

        $open = $this->createTurnamen(
            '[SEED] Group Match — Siap Tutup Pendaftaran',
            'friendly',
            'open',
            200000,
            '16 pemain approved, 4 per grup — siap Tutup Pendaftaran + buat kerangka grup.',
            $approved,
            $playersPerGroup
        );
        $this->registerApprovedIndividuals($open, $approved, 'Friendly Open');

        $ongoing = $this->createTurnamen(
            '[SEED] Group Match — Ongoing Liga',
            'friendly',
            'open',
            200000,
            'Group Match ongoing: grup penuh + slot pertandingan antar grup.',
            $approved,
            $playersPerGroup
        );
        $this->registerApprovedIndividuals($ongoing, $approved, 'Friendly Ongoing');
        $this->groupService->closeRegistration($ongoing);
        $ongoing->refresh();
        $this->friendlyService->generateGroups($ongoing, 'random');

        return ['jenis' => 'friendly', 'open' => $open, 'ongoing' => $ongoing, 'approved' => $approved];
    }

    protected function createTurnamen(
        string $nama,
        string $jenis,
        string $status,
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
            'status' => $status,
            'maks_peserta' => $maksPeserta,
            'mahjong_is_final' => false,
            'registration_paired_at' => null,
            'group_matches_generated_at' => null,
        ];

        if ($jenis === 'friendly') {
            $payload['players_per_group'] = $playersPerGroup
                ?? Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP;
        }

        return Turnamen::create($payload);
    }

    protected function registerApprovedIndividuals(Turnamen $turnamen, int $count, string $prefix): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $pemain = $this->createPemain(
                "{$prefix} " . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                $i % 2 ? 'male' : 'female',
                2.0 + ($i * 0.05)
            );
            $this->registerPlayer($turnamen, $pemain, 'approved');
        }
    }

    protected function registerApprovedPairs(Turnamen $turnamen, int $pairCount, string $prefix): void
    {
        for ($i = 1; $i <= $pairCount; $i++) {
            $a = $this->createPemain(
                "{$prefix} A" . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'male',
                2.0 + ($i * 0.05)
            );
            $b = $this->createPemain(
                "{$prefix} B" . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'female',
                2.1 + ($i * 0.05)
            );

            $pesertaA = $this->registerPlayer($turnamen, $a, 'approved');
            $pesertaB = $this->registerPlayer($turnamen, $b, 'approved');

            $this->pairingService->createPair($turnamen, $pesertaA, $pesertaB);
        }
    }

    protected function registerPlayer(Turnamen $turnamen, Pemain $pemain, string $status): TurnamenPeserta
    {
        return TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => $status,
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

        // Unique prefix to avoid colliding with dump / other seeders.
        return '+6281999' . str_pad((string) $this->phoneSeq, 6, '0', STR_PAD_LEFT);
    }

    protected function ensureAdminUsers(): void
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
            $user = \App\Models\User::where('username', $account['username'])->first();

            if ($user) {
                $user->update(array_merge($account, ['password' => $password]));
            } else {
                \App\Models\User::create(array_merge($account, [
                    'password' => $password,
                    'id_turnamen' => null,
                ]));
            }
        }
    }
}
