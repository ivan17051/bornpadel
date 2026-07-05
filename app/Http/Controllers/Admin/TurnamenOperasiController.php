<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GroupMatchmakingService;
use App\Services\MatchmakingPageService;
use App\Services\RegisteredPemainListingService;
use Illuminate\Http\Request;

class TurnamenOperasiController extends Controller
{
    public function index(
        Request $request,
        GroupMatchmakingService $matchmakingService,
        RegisteredPemainListingService $pemainListingService,
        MatchmakingPageService $matchmakingPageService
    ) {
        $turnamenList = $matchmakingService->listForFilter();
        $turnamen = $matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );

        $listing = $pemainListingService->paginate($request, $turnamen);
        $matchmaking = $matchmakingPageService->getIndexData($request, $turnamen);

        $activeTab = $request->query('tab', 'pemain');
        if (! in_array($activeTab, ['pemain', 'matchmaking'], true)) {
            $activeTab = 'pemain';
        }

        return view('admin.turnamen-operasi.index', array_merge(
            [
                'turnamenList' => $turnamenList,
                'turnamen' => $turnamen,
                'activeTab' => $activeTab,
                'filterRoute' => route('admin.turnamen-operasi.index'),
            ],
            $listing,
            $matchmaking
        ));
    }
}
