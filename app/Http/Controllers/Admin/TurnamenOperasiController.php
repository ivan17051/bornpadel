<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\LeaderboardService;
use App\Services\MatchmakingPageService;
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
        KnockoutBracketService $bracketService
    ) {
        $turnamenList = $matchmakingService->listForFilter();
        $turnamen = $matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );

        $activeTab = $request->query('tab', 'pemain');
        $allowedTabs = ['pemain', 'matchmaking', 'klasemen'];

        if ($turnamen && ! $turnamen->isMahjong()) {
            $allowedTabs[] = 'bracket';
        }

        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'pemain';
        }

        $listing = $pemainListingService->paginate($request, $turnamen);
        $matchmaking = $matchmakingPageService->getIndexData($request, $turnamen);

        $standings = collect();
        $bracket = [];

        if ($turnamen && $activeTab === 'klasemen') {
            if ($turnamen->isMahjong()) {
                $mahjongStandings = $leaderboardService->getMahjongStandingsByBabak($turnamen->id);
                $standings = $mahjongStandings['sections'];
            } else {
                $standings = $leaderboardService->getStandings($turnamen->id);
            }
        }

        if ($turnamen && $activeTab === 'bracket' && ! $turnamen->isMahjong()) {
            $bracket = $bracketService->getBracketTree($turnamen);
        }

        return view('admin.turnamen-operasi.index', array_merge(
            [
                'turnamenList' => $turnamenList,
                'turnamen' => $turnamen,
                'activeTab' => $activeTab,
                'filterRoute' => route('admin.turnamen-operasi.index'),
                'standings' => $standings,
                'bracket' => $bracket,
            ],
            $listing,
            $matchmaking
        ));
    }
}
