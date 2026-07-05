<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\TournamentAccessService;

class DashboardController extends Controller
{
    public function index(
        TournamentAccessService $tournamentAccess,
        DashboardService $dashboardService
    ) {
        $isAdmin = $tournamentAccess->isAdmin();
        $scopedTurnamenId = $tournamentAccess->isPanitia()
            ? $tournamentAccess->assignedTurnamenId()
            : null;

        $globalStats = $dashboardService->getGlobalStats($isAdmin);
        $registrationStats = $dashboardService->getGlobalRegistrationStats($scopedTurnamenId);
        $recentTurnamen = $isAdmin
            ? $dashboardService->getRecentTurnamen(20)
            : collect();
        $recentRegistrations = $dashboardService->getAllRecentRegistrations($scopedTurnamenId);

        return view('admin.dashboard', [
            'globalStats' => $globalStats,
            'registrationStats' => $registrationStats,
            'recentTurnamen' => $recentTurnamen,
            'recentRegistrations' => $recentRegistrations,
            'isAdmin' => $isAdmin,
            'assignedTurnamen' => $tournamentAccess->assignedTurnamen(),
        ]);
    }
}
