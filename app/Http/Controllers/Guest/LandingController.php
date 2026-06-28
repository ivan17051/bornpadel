<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Services\PemainRegistrationService;

class LandingController extends Controller
{
    protected $registrationService;

    public function __construct(PemainRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function index()
    {
        $publicTournaments = $this->registrationService->getPublicTournaments();

        return view('guest.landing', compact('publicTournaments'));
    }
}
