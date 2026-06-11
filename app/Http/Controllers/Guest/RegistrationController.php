<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\LookupPemainRegistrationRequest;
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

    public function lookup(LookupPemainRegistrationRequest $request)
    {
        $turnamen = $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        $noHp = trim($request->validated()['no_hp']);
        $existingPemain = $this->registrationService->findPemainByPhone($noHp);

        if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $turnamen)) {
            return back()
                ->withInput()
                ->withErrors(['no_hp' => 'Nomor HP sudah terdaftar pada turnamen ini.']);
        }

        return redirect()->route('guest.register.form', ['no_hp' => $noHp]);
    }

    public function form()
    {
        $turnamen = $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        $noHp = trim((string) request('no_hp', old('no_hp', '')));

        if ($noHp === '') {
            return redirect()->route('guest.register');
        }

        $existingPemain = $this->registrationService->findPemainByPhone($noHp);

        if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $turnamen)) {
            return redirect()->route('guest.register')
                ->withErrors(['no_hp' => 'Nomor HP sudah terdaftar pada turnamen ini.']);
        }

        return view('guest.register-form', [
            'turnamen' => $turnamen,
            'noHp' => $noHp,
            'existingPemain' => $existingPemain,
            'isExisting' => (bool) $existingPemain,
        ]);
    }

    public function store(StorePemainRegistrationRequest $request)
    {
        $turnamen = $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        try {
            $pemain = $this->registrationService->register(
                $turnamen,
                $request->validated(),
                $request->file('foto')
            );
        } catch (\RuntimeException $e) {
            $field = str_contains($e->getMessage(), 'gambar') || str_contains($e->getMessage(), 'WebP') || str_contains($e->getMessage(), 'Foto')
                ? 'foto'
                : 'no_hp';

            return redirect()
                ->route('guest.register.form', ['no_hp' => $request->input('no_hp')])
                ->withInput()
                ->withErrors([$field => $e->getMessage()]);
        }

        return redirect()
            ->route('guest.register.success', ['pemain' => $pemain->id])
            ->with('registered_pemain', [
                'id' => $pemain->id,
                'nama' => $pemain->nama,
                'no_hp' => $pemain->no_hp,
                'status' => $this->registrationService->getRegistrationStatus($pemain, $turnamen),
            ]);
    }

    public function success()
    {
        $pemain = session('registered_pemain');

        if (! $pemain) {
            return redirect()->route('guest.landing');
        }

        $turnamen = $this->registrationService->getActiveTournament();
        $pemainModel = isset($pemain['id']) ? \App\Models\Pemain::find($pemain['id']) : null;

        return view('guest.register-success', compact('pemain', 'turnamen', 'pemainModel'));
    }
}
