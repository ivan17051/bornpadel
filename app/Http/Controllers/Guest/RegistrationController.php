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

        $existingPartner = null;
        $partnerNoHp = trim((string) old('partner_no_hp', ''));

        if ($turnamen->isDouble() && $partnerNoHp !== '') {
            $existingPartner = $this->registrationService->findPemainByPhone($partnerNoHp);

            if ($existingPartner && $this->registrationService->isRegisteredForTournament($existingPartner, $turnamen)) {
                $existingPartner = null;
            }
        }

        return view('guest.register-form', [
            'turnamen' => $turnamen,
            'noHp' => $noHp,
            'existingPemain' => $existingPemain,
            'isExisting' => (bool) $existingPemain,
            'existingPartner' => $existingPartner,
        ]);
    }

    public function store(StorePemainRegistrationRequest $request)
    {
        $turnamen = $this->registrationService->getActiveTournament();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        $validated = $request->validated();

        try {
            if ($turnamen->isDouble()) {
                $result = $this->registrationService->registerPair(
                    $turnamen,
                    $validated,
                    $request->file('foto'),
                    [
                        'no_hp' => $validated['partner_no_hp'],
                        'nama' => $validated['partner_nama'],
                        'tgl_lahir' => $validated['partner_tgl_lahir'],
                        'gender' => $validated['partner_gender'],
                        'rating' => $validated['partner_rating'] ?? null,
                    ],
                    $request->file('partner_foto')
                );

                $pemain = $result['pemain'];
                $partner = $result['partner'];

                return redirect()
                    ->route('guest.register.success', ['pemain' => $pemain->id])
                    ->with('registered_pemain', [
                        'id' => $pemain->id,
                        'nama' => $pemain->nama,
                        'no_hp' => $pemain->no_hp,
                        'status' => $this->registrationService->getRegistrationStatus($pemain, $turnamen),
                        'partner' => [
                            'id' => $partner->id,
                            'nama' => $partner->nama,
                            'no_hp' => $partner->no_hp,
                            'status' => $this->registrationService->getRegistrationStatus($partner, $turnamen),
                        ],
                    ]);
            }

            $pemain = $this->registrationService->register(
                $turnamen,
                $validated,
                $request->file('foto')
            );
        } catch (\RuntimeException $e) {
            $field = 'no_hp';

            if (str_contains($e->getMessage(), 'pemain 2')) {
                $field = 'partner_no_hp';
            } elseif (str_contains($e->getMessage(), 'gambar') || str_contains($e->getMessage(), 'WebP') || str_contains($e->getMessage(), 'Foto')) {
                $field = str_contains($e->getMessage(), 'pemain 2') ? 'partner_foto' : 'foto';
            }

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
        $partnerModel = isset($pemain['partner']['id']) ? \App\Models\Pemain::find($pemain['partner']['id']) : null;
        $partner = $pemain['partner'] ?? null;

        return view('guest.register-success', compact('pemain', 'partner', 'turnamen', 'pemainModel', 'partnerModel'));
    }
}
