<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\LookupPemainRegistrationRequest;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Models\Pemain;
use App\Models\Turnamen;
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
        $openTournaments = $this->registrationService->getOpenTournaments();

        if ($openTournaments->isEmpty()) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Tidak ada turnamen yang sedang dibuka untuk pendaftaran.');
        }

        $turnamen = $this->resolveRegistrationTurnamen();

        if (! $turnamen) {
            return view('guest.register-select', compact('openTournaments'));
        }

        return view('guest.register', compact('turnamen', 'openTournaments'));
    }

    public function lookup(LookupPemainRegistrationRequest $request)
    {
        $turnamen = $this->resolveRegistrationTurnamen();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        $validated = $request->validated();
        $noHp = trim($validated['no_hp']);
        $registrationMode = $validated['registration_mode'] ?? 'single';
        $isPairMode = $turnamen->isDouble() && $registrationMode === 'pair';
        $noHp2 = $isPairMode ? trim($validated['no_hp_2']) : null;

        $existingPemain = $this->registrationService->findPemainByPhone($noHp);

        if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $turnamen)) {
            return back()
                ->withInput()
                ->withErrors(['no_hp' => 'Nomor HP pemain 1 sudah terdaftar pada turnamen ini.']);
        }

        if ($isPairMode) {
            $existingPemain2 = $this->registrationService->findPemainByPhone($noHp2);

            if ($existingPemain2 && $this->registrationService->isRegisteredForTournament($existingPemain2, $turnamen)) {
                return back()
                    ->withInput()
                    ->withErrors(['no_hp_2' => 'Nomor HP pemain 2 sudah terdaftar pada turnamen ini.']);
            }
        }

        $formParams = [
            'no_hp' => $noHp,
            'id_turnamen' => $turnamen->id,
            'registration_mode' => $isPairMode ? 'pair' : 'single',
        ];

        if ($isPairMode) {
            $formParams['no_hp_2'] = $noHp2;
        }

        return redirect()->route('guest.register.form', $formParams);
    }

    public function form()
    {
        $turnamen = $this->resolveRegistrationTurnamen();

        if (! $turnamen) {
            return redirect()->route('guest.register')
                ->with('warning', 'Pilih turnamen terlebih dahulu.');
        }

        $noHp = trim((string) request('no_hp', old('no_hp', '')));
        $registrationMode = request('registration_mode', old('registration_mode', 'single'));
        $isPairMode = $turnamen->isDouble() && $registrationMode === 'pair';
        $noHp2 = $isPairMode ? trim((string) request('no_hp_2', old('player_2.no_hp', ''))) : '';

        if ($noHp === '') {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id]);
        }

        if ($isPairMode && $noHp2 === '') {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                ->withErrors(['no_hp_2' => 'Nomor HP pemain 2 wajib diisi untuk pendaftaran berpasangan.']);
        }

        $existingPemain = $this->registrationService->findPemainByPhone($noHp);

        if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $turnamen)) {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                ->withErrors(['no_hp' => 'Nomor HP pemain 1 sudah terdaftar pada turnamen ini.']);
        }

        $existingPemain2 = null;
        $isExisting2 = false;

        if ($isPairMode) {
            $existingPemain2 = $this->registrationService->findPemainByPhone($noHp2);

            if ($existingPemain2 && $this->registrationService->isRegisteredForTournament($existingPemain2, $turnamen)) {
                return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                    ->withErrors(['no_hp_2' => 'Nomor HP pemain 2 sudah terdaftar pada turnamen ini.']);
            }

            $isExisting2 = (bool) $existingPemain2;
        }

        return view('guest.register-form', [
            'turnamen' => $turnamen,
            'noHp' => $noHp,
            'noHp2' => $noHp2,
            'existingPemain' => $existingPemain,
            'existingPemain2' => $existingPemain2,
            'isExisting' => (bool) $existingPemain,
            'isExisting2' => $isExisting2,
            'registrationMode' => $isPairMode ? 'pair' : 'single',
        ]);
    }

    public function store(StorePemainRegistrationRequest $request)
    {
        $turnamen = $this->resolveRegistrationTurnamen();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        $validated = $request->validated();
        $buktiBayar = $request->file('bukti_bayar');

        try {
            $registerAsPair = $turnamen->isDouble() && $request->isPairRegistration();

            if ($registerAsPair) {
                $pair = $this->registrationService->registerPair(
                    $turnamen,
                    $request->playerOnePayload(),
                    $request->file('foto'),
                    $request->playerTwoPayload(),
                    $request->file('foto_2'),
                    $buktiBayar
                );

                $pemain = $pair['pemain'];
                $partner = $pair['partner'];
            } else {
                $pemain = $this->registrationService->register(
                    $turnamen,
                    $request->playerOnePayload(),
                    $request->file('foto'),
                    $buktiBayar
                );
                $partner = null;
            }
        } catch (\RuntimeException $e) {
            $field = 'no_hp';

            if (str_contains($e->getMessage(), 'gambar') || str_contains($e->getMessage(), 'WebP') || str_contains($e->getMessage(), 'Foto')) {
                $field = 'foto';
            }

            return redirect()
                ->route('guest.register.form', array_filter([
                    'no_hp' => $request->input('no_hp'),
                    'no_hp_2' => $request->input('player_2.no_hp'),
                    'registration_mode' => $request->input('registration_mode'),
                    'id_turnamen' => $turnamen->id,
                ]))
                ->withInput()
                ->withErrors([$field => $e->getMessage()]);
        }

        return redirect()
            ->route('guest.register.success')
            ->with('registration_success', [
                'is_double' => $turnamen->isDouble(),
                'individual_registration' => $turnamen->isDouble() && ! $partner,
                'paired_registration' => (bool) $partner,
                'turnamen_id' => $turnamen->id,
                'players' => array_values(array_filter([
                    $this->playerPayload($pemain, $turnamen),
                    $partner ? $this->playerPayload($partner, $turnamen) : null,
                ])),
            ]);
    }

    public function success()
    {
        $players = $this->resolveRegistrationPlayers();

        if (empty($players)) {
            return redirect()->route('guest.landing');
        }

        $registration = session('registration_success', []);
        $turnamen = ! empty($registration['turnamen_id'])
            ? Turnamen::find($registration['turnamen_id'])
            : $this->resolveRegistrationTurnamen();
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

            return $players;
        }

        return $registration['players'] ?? [];
    }

    protected function resolveRegistrationTurnamen(): ?Turnamen
    {
        $turnamenId = request()->input('id_turnamen') ?? old('id_turnamen');

        if ($turnamenId) {
            return $this->registrationService->resolveOpenTournament((int) $turnamenId);
        }

        $openTournaments = $this->registrationService->getOpenTournaments();

        return $openTournaments->count() === 1 ? $openTournaments->first() : null;
    }
}
