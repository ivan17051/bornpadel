<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\TournamentAccessService;

class DashboardController extends Controller
{
    public function index(
        GroupMatchmakingService $matchmakingService,
        TournamentAccessService $tournamentAccess
    ) {
        $turnamen = $matchmakingService->resolveTournament(
            request()->filled('id_turnamen') ? (int) request('id_turnamen') : null
        );

        $pendingPemain = 0;
        $approvedPemain = 0;
        $totalPemain = Pemain::count();

        if ($turnamen) {
            $pendingPemain = TurnamenPeserta::where('id_turnamen', $turnamen->id)
                ->where('status', 'pending')
                ->count();
            $approvedPemain = TurnamenPeserta::where('id_turnamen', $turnamen->id)
                ->where('status', 'approved')
                ->count();
        }

        $totalPertandingan = $turnamen
            ? Pertandingan::where('id_turnamen', $turnamen->id)->count()
            : Pertandingan::count();

        if ($tournamentAccess->isPanitia()) {
            $totalPemain = $turnamen
                ? TurnamenPeserta::where('id_turnamen', $turnamen->id)->count()
                : 0;
        }

        return view('admin.dashboard', [
            'stats' => [
                'total_pemain' => $totalPemain,
                'pending_pemain' => $pendingPemain,
                'approved_pemain' => $approvedPemain,
                'total_pertandingan' => $totalPertandingan,
            ],
            'turnamen' => $turnamen,
        ]);
    }
}
