<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\LeaderboardService;
use App\Services\MatchmakingPageService;
use App\Services\FriendlyMatchmakingService;
use App\Services\RegisteredPemainListingService;
use Illuminate\Http\Request;

class TurnamenOperasiController extends Controller
{
    public function index(
        Request $request,
        GroupMatchmakingService $matchmakingService,
        RegisteredPemainListingService $pemainListingService,
        MatchmakingPageService $matchmakingPageService,
        LeaderboardService $leaderboardService,
        KnockoutBracketService $bracketService,
        FriendlyMatchmakingService $friendlyService
    ) {
        $turnamenList = $matchmakingService->listForFilter();
        $turnamen = $matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );

        $activeTab = $request->query('tab', 'pemain');
        $allowedTabs = ['pemain', 'matchmaking', 'klasemen'];

        if ($turnamen && ! $turnamen->isMahjong() && ! $turnamen->isFriendly()) {
            $allowedTabs[] = 'bracket';
        }

        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'pemain';
        }

        $kategori = null;
        $kategoriId = null;
        $kategoriList = collect();

        if ($turnamen) {
            $kategoriList = $turnamen->kategori()->ordered()->get();
            try {
                $kategori = $turnamen->resolveKategori(
                    $request->filled('id_kategori') ? (int) $request->id_kategori : null
                );
            } catch (\RuntimeException $e) {
                $kategori = $turnamen->resolveKategori();
            }
            $kategoriId = (int) $kategori->id;
            // Normalize query when multi-category so bookmarks stay coherent.
            if ($turnamen->hasMultipleKategori() && ! $request->filled('id_kategori')) {
                $request->merge(['id_kategori' => $kategoriId]);
            }
        }

        $listing = $pemainListingService->paginate($request, $turnamen, $kategoriId);
        $matchmaking = $matchmakingPageService->getIndexData($request, $turnamen);

        $standings = collect();
        $friendlyMatchSessions = collect();
        $bracket = [];

        if ($turnamen && $activeTab === 'klasemen') {
            if ($turnamen->isMahjong()) {
                $mahjongStandings = $leaderboardService->getMahjongStandingsByBabak($turnamen->id, $kategoriId);
                $standings = $mahjongStandings['sections'];
            } else {
                $standings = $leaderboardService->getStandings($turnamen->id, $kategoriId);
            }

            if ($turnamen->isFriendly()) {
                $friendlyMatchSessions = $friendlyService->getPublicMatchSessions($turnamen, $kategoriId);
            }
        }

        if ($turnamen && $activeTab === 'bracket' && ! $turnamen->isMahjong() && ! $turnamen->isFriendly()) {
            $bracket = $bracketService->getBracketTree($turnamen, $kategoriId);
        }

        return view('admin.turnamen-operasi.index', array_merge(
            [
                'turnamenList' => $turnamenList,
                'turnamen' => $turnamen,
                'kategori' => $kategori,
                'kategoriList' => $kategoriList,
                'kategoriId' => $kategoriId,
                'activeTab' => $activeTab,
                'filterRoute' => route('admin.turnamen-operasi.index'),
                'standings' => $standings,
                'friendlyMatchSessions' => $friendlyMatchSessions,
                'bracket' => $bracket,
            ],
            $listing,
            $matchmaking
        ));
    }
}
