<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FriendlyMatchmakingService;
use App\Services\GroupMatchmakingService;
use App\Services\LeaderboardService;
use App\Services\MahjongMatchmakingService;
use App\Services\MahjongTeamMatchmakingService;
use Illuminate\Http\Request;
use RuntimeException;

class StandingsController extends Controller
{
    public function index(
        Request $request,
        LeaderboardService $leaderboardService,
        GroupMatchmakingService $matchmakingService,
        FriendlyMatchmakingService $friendlyService,
        MahjongMatchmakingService $mahjongService,
        MahjongTeamMatchmakingService $mahjongTeamService
    ) {
        $turnamenList = $matchmakingService->listForFilter();
        $turnamen = $matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );

        $kategori = null;
        $kategoriList = collect();
        $kategoriId = null;

        if ($turnamen) {
            $kategoriList = $turnamen->kategori()->ordered()->get();
            try {
                $kategori = $turnamen->resolveKategori(
                    $request->filled('id_kategori') ? (int) $request->id_kategori : null
                );
            } catch (RuntimeException $e) {
                $kategori = $turnamen->resolveKategori();
            }
            $kategoriId = (int) $kategori->id;
        }

        $mahjongStandings = $turnamen && $turnamen->isMahjong()
            ? $leaderboardService->getMahjongStandingsByBabak($turnamen->id, $kategoriId)
            : ['sections' => collect(), 'recap' => collect(), 'babak_numbers' => collect()];
        $standings = $turnamen
            ? ($turnamen->isMahjong()
                ? $mahjongStandings['sections']
                : $leaderboardService->getStandings($turnamen->id, $kategoriId))
            : collect();

        $friendlyMatchSessions = $turnamen && $turnamen->isFriendly()
            ? $friendlyService->getPublicMatchSessions($turnamen, $kategoriId)
            : collect();

        $mahjongHistory = $turnamen && $turnamen->isMahjong()
            ? $mahjongService->getMatchmakingHistory($turnamen, $kategoriId)
            : collect();
        $mahjongTeamHistory = $turnamen && $turnamen->isMahjongTeam()
            ? $mahjongTeamService->getMatchmakingHistory($turnamen, $kategoriId)
            : collect();

        return view('admin.standings.index', compact(
            'turnamen',
            'turnamenList',
            'kategori',
            'kategoriList',
            'standings',
            'friendlyMatchSessions',
            'mahjongHistory',
            'mahjongTeamHistory'
        ));
    }
}
