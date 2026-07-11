<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardService
{
    protected $matchmakingService;
    protected $leaderboardService;
    protected $knockoutBracketService;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        LeaderboardService $leaderboardService,
        KnockoutBracketService $knockoutBracketService
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->leaderboardService = $leaderboardService;
        $this->knockoutBracketService = $knockoutBracketService;
    }

    public function getGlobalStats(bool $isAdmin): array
    {
        $stats = [
            'total_turnamen' => Turnamen::count(),
            'turnamen_open' => Turnamen::where('status', 'open')->count(),
            'turnamen_ongoing' => Turnamen::where('status', 'ongoing')->count(),
            'turnamen_completed' => Turnamen::where('status', 'completed')->count(),
            'total_pemain_directory' => Pemain::count(),
        ];

        if ($isAdmin) {
            $stats['total_users'] = User::count();
            $stats['turnamen_draft'] = Turnamen::where('status', 'draft')->count();
        }

        return $stats;
    }

    public function getTournamentStats(Turnamen $turnamen): array
    {
        $pesertaQuery = TurnamenPeserta::where('id_turnamen', $turnamen->id);

        $registration = [
            'total' => (clone $pesertaQuery)->count(),
            'pending' => (clone $pesertaQuery)->where('status', 'pending')->count(),
            'unpaid' => (clone $pesertaQuery)->where('status', 'unpaid')->count(),
            'paid' => (clone $pesertaQuery)->where('status', 'paid')->count(),
            'approved' => (clone $pesertaQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $pesertaQuery)->where('status', 'rejected')->count(),
            'needs_review' => (clone $pesertaQuery)->whereIn('status', ['pending', 'paid'])->count(),
        ];

        $matchesQuery = Pertandingan::where('id_turnamen', $turnamen->id);

        $stats = [
            'registration' => $registration,
            'approved_entries' => $this->matchmakingService->countApprovedPlayers($turnamen),
            'grup_total' => Grup::where('id_turnamen', $turnamen->id)->count(),
            'grup_active' => $turnamen->activeGrup()->count(),
            'current_babak' => $turnamen->isMahjong()
                ? (int) ($turnamen->activeGrup()->max('babak') ?: $turnamen->grup()->max('babak') ?: 0)
                : null,
            'mahjong_is_final' => (bool) $turnamen->mahjong_is_final,
            'matches_total' => (clone $matchesQuery)->count(),
            'matches_completed' => (clone $matchesQuery)->where('status', 'completed')->count(),
            'matches_ongoing' => (clone $matchesQuery)->where('status', 'ongoing')->count(),
            'matches_scheduled' => (clone $matchesQuery)->where('status', 'scheduled')->count(),
            'has_knockout' => $this->knockoutBracketService->hasKnockoutBracket($turnamen),
            'champion' => $turnamen->champion_label,
        ];

        if ($turnamen->isMahjong()) {
            $stats['group_matches_total'] = 0;
            $stats['knockout_matches_total'] = 0;
        } else {
            $stats['group_matches_total'] = Pertandingan::where('id_turnamen', $turnamen->id)
                ->whereNotNull('id_grup')
                ->count();
            $stats['knockout_matches_total'] = Pertandingan::where('id_turnamen', $turnamen->id)
                ->whereNull('id_grup')
                ->count();
        }

        return $stats;
    }

    public function getGlobalRegistrationStats(?int $turnamenId = null): array
    {
        $query = TurnamenPeserta::query();

        if ($turnamenId) {
            $query->where('id_turnamen', $turnamenId);
        }

        return [
            'total' => (clone $query)->count(),
            'needs_review' => (clone $query)->whereIn('status', ['pending', 'paid'])->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'paid' => (clone $query)->where('status', 'paid')->count(),
        ];
    }

    public function getAllRecentRegistrations(?int $turnamenId = null, int $limit = 10): Collection
    {
        $query = TurnamenPeserta::query()
            ->with(['pemain1', 'pasanganAsPeserta1.peserta2.pemain1', 'turnamen'])
            ->whereIn('status', ['pending', 'paid', 'unpaid']);

        if ($turnamenId) {
            $query->where('id_turnamen', $turnamenId);
        }

        return $query->latest('updated_at')->limit($limit)->get();
    }

    public function getRecentTurnamen(int $limit = 8): Collection
    {
        return Turnamen::query()
            ->withCount([
                'turnamenPeserta',
                'turnamenPeserta as approved_count' => function ($query) {
                    $query->where('status', 'approved');
                },
                'grup',
                'pertandingan',
            ])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function getRecentRegistrations(Turnamen $turnamen, int $limit = 8): Collection
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->with(['pemain1', 'pasanganAsPeserta1.peserta2.pemain1'])
            ->whereIn('status', ['pending', 'paid', 'unpaid'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    public function getTopStandingsPreview(Turnamen $turnamen, int $limit = 5): Collection
    {
        if ($turnamen->isMahjong()) {
            return $this->leaderboardService
                ->getMahjongGlobalStandings($turnamen->id)
                ->take($limit);
        }

        return $this->leaderboardService
            ->getStandings($turnamen->id)
            ->flatMap(function (array $grup) {
                return $grup['standings']->map(function (array $row) use ($grup) {
                    $row['grup_nama'] = $grup['nama'];

                    return $row;
                });
            })
            ->sort(function (array $a, array $b) {
                return GrupMember::comparePadelStandingRows($a, $b);
            })
            ->take($limit)
            ->values();
    }

    public function getWinners(Turnamen $turnamen): Collection
    {
        if ($turnamen->status !== 'completed') {
            return collect();
        }

        return $turnamen->pemenang()
            ->with('pemain')
            ->orderBy('peringkat')
            ->get();
    }
}
