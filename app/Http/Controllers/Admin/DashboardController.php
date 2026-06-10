<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Services\GroupMatchmakingService;

class DashboardController extends Controller
{
    public function index(GroupMatchmakingService $matchmakingService)
    {
        $turnamen = $matchmakingService->getActiveTournament();

        return view('admin.dashboard', [
            'stats' => [
                'total_pemain' => Pemain::count(),
                'pending_pemain' => Pemain::where('status', 'pending')->count(),
                'approved_pemain' => Pemain::where('status', 'approved')->count(),
                'total_pertandingan' => Pertandingan::count(),
            ],
            'turnamen' => $turnamen,
        ]);
    }
}
