<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Services\PemainRegistrationService;

class RegistrationController extends Controller
{
    protected $registrationService;

    public function __construct(PemainRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function create()
    {
        $turnamen = $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Tidak ada turnamen yang sedang dibuka untuk pendaftaran.');
        }

        return view('guest.register', compact('turnamen'));
    }

    public function store(StorePemainRegistrationRequest $request)
    {
        $turnamen = $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        $pemain = $this->registrationService->register($request->validated());

        return redirect()
            ->route('guest.register.success', ['pemain' => $pemain->id])
            ->with('registered_pemain', $pemain->only(['id', 'nama', 'no_hp', 'status']));
    }

    public function success()
    {
        $pemain = session('registered_pemain');

        if (! $pemain) {
            return redirect()->route('guest.landing');
        }

        $turnamen = $this->registrationService->getActiveTournament();

        return view('guest.register-success', compact('pemain', 'turnamen'));
    }
}
