<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\PemainRegistrationService;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    protected $registrationService;

    public function __construct(PemainRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function index(Request $request)
    {
        $activeTournaments = $this->registrationService->getPublicActiveTournaments();

        $completedFilter = $this->registrationService->resolvePublicCompletedFilter(
            $request->filled('completed_month') ? (int) $request->completed_month : null,
            $request->filled('completed_year') ? (int) $request->completed_year : null
        );

        $completedTournaments = $completedFilter['hasAny']
            ? $this->registrationService->getPublicCompletedTournaments(
                $completedFilter['month'],
                $completedFilter['year']
            )
            : collect();

        $completedMonths = $completedFilter['hasAny']
            ? $this->registrationService->getPublicCompletedMonthsForYear($completedFilter['year'])
            : [];

        return view('guest.landing', [
            'activeTournaments' => $activeTournaments,
            'completedTournaments' => $completedTournaments,
            'completedFilter' => $completedFilter,
            'completedMonths' => $completedMonths,
        ]);
    }
}
