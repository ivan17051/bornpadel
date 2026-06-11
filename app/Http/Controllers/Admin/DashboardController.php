<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;

class DashboardController extends Controller
{
    public function index(GroupMatchmakingService $matchmakingService)
    {
        $turnamen = $matchmakingService->getActiveTournament();

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

        return view('admin.dashboard', [
            'stats' => [
                'total_pemain' => $totalPemain,
                'pending_pemain' => $pendingPemain,
                'approved_pemain' => $approvedPemain,
                'total_pertandingan' => Pertandingan::count(),
            ],
            'turnamen' => $turnamen,
        ]);
    }
}
