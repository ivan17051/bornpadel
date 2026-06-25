<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\LookupPemainRegistrationRequest;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Models\Pemain;
use App\Models\TurnamenPeserta;
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

        $validated = $request->validated();
        $noHp = trim($validated['no_hp']);
        $existingPemain = $this->registrationService->findPemainByPhone($noHp);

        if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $turnamen)) {
            return back()
                ->withInput()
                ->withErrors(['no_hp' => 'Nomor HP pemain 1 sudah terdaftar pada turnamen ini.']);
        }

        if ($turnamen->isDouble()) {
            $partnerNoHp = trim($validated['partner_no_hp']);
            $existingPartner = $this->registrationService->findPemainByPhone($partnerNoHp);

            if ($existingPartner && $this->registrationService->isRegisteredForTournament($existingPartner, $turnamen)) {
                return back()
                    ->withInput()
                    ->withErrors(['partner_no_hp' => 'Nomor HP pemain 2 sudah terdaftar pada turnamen ini.']);
            }

            return redirect()->route('guest.register.form', [
                'no_hp' => $noHp,
                'partner_no_hp' => $partnerNoHp,
            ]);
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
                ->withErrors(['no_hp' => 'Nomor HP pemain 1 sudah terdaftar pada turnamen ini.']);
        }

        $partnerNoHp = '';
        $existingPartner = null;
        $isPartnerExisting = false;

        if ($turnamen->isDouble()) {
            $partnerNoHp = trim((string) request('partner_no_hp', old('partner_no_hp', '')));

            if ($partnerNoHp === '') {
                return redirect()->route('guest.register');
            }

            if ($noHp === $partnerNoHp) {
                return redirect()->route('guest.register')
                    ->withErrors(['partner_no_hp' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.']);
            }

            $existingPartner = $this->registrationService->findPemainByPhone($partnerNoHp);
            $isPartnerExisting = (bool) $existingPartner;

            if ($existingPartner && $this->registrationService->isRegisteredForTournament($existingPartner, $turnamen)) {
                return redirect()->route('guest.register')
                    ->withErrors(['partner_no_hp' => 'Nomor HP pemain 2 sudah terdaftar pada turnamen ini.']);
            }
        }

        return view('guest.register-form', [
            'turnamen' => $turnamen,
            'noHp' => $noHp,
            'partnerNoHp' => $partnerNoHp,
            'existingPemain' => $existingPemain,
            'isExisting' => (bool) $existingPemain,
            'existingPartner' => $existingPartner,
            'isPartnerExisting' => $isPartnerExisting,
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
                    ->route('guest.register.success')
                    ->with('registration_success', [
                        'is_double' => true,
                        'turnamen_id' => $turnamen->id,
                        'players' => [
                            $this->playerPayload($pemain, $turnamen),
                            $this->playerPayload($partner, $turnamen),
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

            $formParams = ['no_hp' => $request->input('no_hp')];

            if ($turnamen->isDouble()) {
                $formParams['partner_no_hp'] = $request->input('partner_no_hp');
            }

            return redirect()
                ->route('guest.register.form', $formParams)
                ->withInput()
                ->withErrors([$field => $e->getMessage()]);
        }

        return redirect()
            ->route('guest.register.success')
            ->with('registration_success', [
                'is_double' => false,
                'turnamen_id' => $turnamen->id,
                'players' => [
                    $this->playerPayload($pemain, $turnamen),
                ],
            ]);
    }

    public function success()
    {
        $players = $this->resolveRegistrationPlayers();

        if (empty($players)) {
            return redirect()->route('guest.landing');
        }

        $turnamen = $this->registrationService->getActiveTournament();
        $playerModels = collect($players)
            ->map(function (array $player) {
                return Pemain::find($player['id'] ?? null);
            })
            ->filter()
            ->values();

        return view('guest.register-success', compact('players', 'playerModels', 'turnamen'));
    }

    protected function playerPayload(Pemain $pemain, $turnamen): array
    {
        return [
            'id' => $pemain->id,
            'nama' => $pemain->nama,
            'no_hp' => $pemain->no_hp,
            'status' => $this->registrationService->getRegistrationStatus($pemain, $turnamen),
        ];
    }

    protected function resolveRegistrationPlayers(): array
    {
        $registration = session('registration_success');

        if (! $registration) {
            $legacy = session('registered_pemain');

            if (! $legacy || ! isset($legacy['id'])) {
                return [];
            }

            $players = [[
                'id' => $legacy['id'],
                'nama' => $legacy['nama'],
                'no_hp' => $legacy['no_hp'],
                'status' => $legacy['status'] ?? null,
            ]];

            if (! empty($legacy['partner'])) {
                $players[] = $legacy['partner'];
            }

            $registration = [
                'is_double' => ! empty($legacy['partner']),
                'players' => $players,
            ];
        }

        $players = $registration['players'] ?? [];
        $turnamen = $this->registrationService->getActiveTournament();

        if ($turnamen && $turnamen->isDouble() && count($players) < 2 && ! empty($players[0]['id'])) {
            $peserta = TurnamenPeserta::query()
                ->forTurnamen($turnamen->id)
                ->where('id_pemain1', $players[0]['id'])
                ->with('pemain2')
                ->latest('id')
                ->first();

            if ($peserta && $peserta->pemain2) {
                $players[] = [
                    'id' => $peserta->pemain2->id,
                    'nama' => $peserta->pemain2->nama,
                    'no_hp' => $peserta->pemain2->no_hp,
                    'status' => $peserta->status,
                ];
            }
        }

        return $players;
    }
}
