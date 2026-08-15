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
        $assignedTurnamenList = $isAdmin ? collect() : $tournamentAccess->assignedTurnamenList();
        $scopedTurnamenIds = $isAdmin ? null : $assignedTurnamenList->pluck('id')->all();

        $globalStats = $dashboardService->getGlobalStats($isAdmin);
        $registrationStats = $dashboardService->getGlobalRegistrationStats($scopedTurnamenIds);
        $recentTurnamen = $isAdmin
            ? $dashboardService->getRecentTurnamen(20)
            : collect();
        $recentRegistrations = $dashboardService->getAllRecentRegistrations($scopedTurnamenIds);

        return view('admin.dashboard', [
            'globalStats' => $globalStats,
            'registrationStats' => $registrationStats,
            'recentTurnamen' => $recentTurnamen,
            'recentRegistrations' => $recentRegistrations,
            'isAdmin' => $isAdmin,
            'assignedTurnamen' => $assignedTurnamenList->count() === 1 ? $assignedTurnamenList->first() : null,
            'assignedTurnamenList' => $assignedTurnamenList,
        ]);
    }
}
