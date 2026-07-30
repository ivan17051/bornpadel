<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\LookupPemainRegistrationRequest;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Models\Pemain;
use App\Models\Turnamen;
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
        $isPairMode = $turnamen->requiresPairRegistration() && $registrationMode === 'pair';
        $isGroupMode = $turnamen->allowsGroupRegistration() && $registrationMode === 'group';
        $noHp2 = ($isPairMode || $isGroupMode) ? trim($validated['no_hp_2'] ?? '') : null;
        $noHp3 = $isGroupMode ? trim($validated['no_hp_3'] ?? '') : null;
        $noHp4 = $isGroupMode ? trim($validated['no_hp_4'] ?? '') : null;
        $namaGrup = $isGroupMode ? trim($validated['nama_grup'] ?? '') : null;

        $phones = array_filter([
            'no_hp' => $noHp,
            'no_hp_2' => $noHp2,
            'no_hp_3' => $noHp3,
            'no_hp_4' => $noHp4,
        ], fn ($value) => $value !== null && $value !== '');

        foreach ($phones as $field => $phone) {
            $existing = $this->registrationService->findPemainByPhone($phone);
            if ($existing && $this->registrationService->isRegisteredForTournament($existing, $turnamen)) {
                $label = $field === 'no_hp' ? 'pemain 1' : 'pemain ' . substr($field, -1);

                return back()
                    ->withInput()
                    ->withErrors([$field => 'Nomor HP ' . $label . ' sudah terdaftar pada turnamen ini.']);
            }
        }

        $formParams = [
            'no_hp' => $noHp,
            'id_turnamen' => $turnamen->id,
            'registration_mode' => $isGroupMode ? 'group' : ($isPairMode ? 'pair' : 'single'),
        ];

        if ($isPairMode || $isGroupMode) {
            $formParams['no_hp_2'] = $noHp2;
        }

        if ($isGroupMode) {
            $formParams['no_hp_3'] = $noHp3;
            $formParams['no_hp_4'] = $noHp4;
            $formParams['nama_grup'] = $namaGrup;
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
        $isPairMode = $turnamen->requiresPairRegistration() && $registrationMode === 'pair';
        $isGroupMode = $turnamen->allowsGroupRegistration() && $registrationMode === 'group';
        $noHp2 = ($isPairMode || $isGroupMode) ? trim((string) request('no_hp_2', old('player_2.no_hp', ''))) : '';
        $noHp3 = $isGroupMode ? trim((string) request('no_hp_3', old('player_3.no_hp', ''))) : '';
        $noHp4 = $isGroupMode ? trim((string) request('no_hp_4', old('player_4.no_hp', ''))) : '';
        $namaGrup = $isGroupMode ? trim((string) request('nama_grup', old('nama_grup', ''))) : '';

        if ($noHp === '') {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id]);
        }

        if ($isPairMode && $noHp2 === '') {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                ->withErrors(['no_hp_2' => 'Nomor HP pemain 2 wajib diisi untuk pendaftaran berpasangan.']);
        }

        if ($isGroupMode && ($noHp2 === '' || $noHp3 === '' || $noHp4 === '' || $namaGrup === '')) {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                ->withErrors(['no_hp' => 'Lengkapi 4 nomor HP dan nama grup untuk pendaftaran satu grup.']);
        }

        $phoneChecks = [
            ['phone' => $noHp, 'field' => 'no_hp', 'label' => 'pemain 1'],
        ];

        if ($isPairMode || $isGroupMode) {
            $phoneChecks[] = ['phone' => $noHp2, 'field' => 'no_hp_2', 'label' => 'pemain 2'];
        }

        if ($isGroupMode) {
            $phoneChecks[] = ['phone' => $noHp3, 'field' => 'no_hp_3', 'label' => 'pemain 3'];
            $phoneChecks[] = ['phone' => $noHp4, 'field' => 'no_hp_4', 'label' => 'pemain 4'];
        }

        foreach ($phoneChecks as $check) {
            $existing = $this->registrationService->findPemainByPhone($check['phone']);
            if ($existing && $this->registrationService->isRegisteredForTournament($existing, $turnamen)) {
                return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                    ->withErrors([$check['field'] => 'Nomor HP ' . $check['label'] . ' sudah terdaftar pada turnamen ini.']);
            }
        }

        $existingPemain = $this->registrationService->findPemainByPhone($noHp);
        $existingPemain2 = ($isPairMode || $isGroupMode) ? $this->registrationService->findPemainByPhone($noHp2) : null;
        $existingPemain3 = $isGroupMode ? $this->registrationService->findPemainByPhone($noHp3) : null;
        $existingPemain4 = $isGroupMode ? $this->registrationService->findPemainByPhone($noHp4) : null;

        return view('guest.register-form', [
            'turnamen' => $turnamen,
            'noHp' => $noHp,
            'noHp2' => $noHp2,
            'noHp3' => $noHp3,
            'noHp4' => $noHp4,
            'namaGrup' => $namaGrup,
            'existingPemain' => $existingPemain,
            'existingPemain2' => $existingPemain2,
            'existingPemain3' => $existingPemain3,
            'existingPemain4' => $existingPemain4,
            'isExisting' => (bool) $existingPemain,
            'isExisting2' => (bool) $existingPemain2,
            'isExisting3' => (bool) $existingPemain3,
            'isExisting4' => (bool) $existingPemain4,
            'registrationMode' => $isGroupMode ? 'group' : ($isPairMode ? 'pair' : 'single'),
        ]);
    }

    public function store(StorePemainRegistrationRequest $request)
    {
        $turnamen = $this->resolveRegistrationTurnamen();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        $buktiBayar = $request->file('bukti_bayar');
        $namaGrup = null;

        try {
            $registerAsGroup = $turnamen->allowsGroupRegistration() && $request->isGroupRegistration();
            $registerAsPair = $turnamen->requiresPairRegistration() && $request->isPairRegistration();

            if ($registerAsGroup) {
                $result = $this->registrationService->registerGroup(
                    $turnamen,
                    (string) $request->input('nama_grup'),
                    $request->groupPlayersPayload(),
                    [
                        $request->file('foto'),
                        $request->file('foto_2'),
                        $request->file('foto_3'),
                        $request->file('foto_4'),
                    ],
                    $buktiBayar,
                    TurnamenPeserta::SUMBER_INTERNAL,
                    false
                );

                $players = $result['players'];
                $pemain = $players[0];
                $partner = null;
                $namaGrup = $result['grup_pendaftaran']->nama;
                $extraPlayers = $players->slice(1)->values();
            } elseif ($registerAsPair) {
                $pair = $this->registrationService->registerPair(
                    $turnamen,
                    $request->playerOnePayload(),
                    $request->file('foto'),
                    $request->playerTwoPayload(),
                    $request->file('foto_2'),
                    $buktiBayar,
                    TurnamenPeserta::SUMBER_INTERNAL,
                    false
                );

                $pemain = $pair['pemain'];
                $partner = $pair['partner'];
                $extraPlayers = collect();
            } else {
                $pemain = $this->registrationService->register(
                    $turnamen,
                    $request->playerOnePayload(),
                    $request->file('foto'),
                    $buktiBayar,
                    TurnamenPeserta::SUMBER_INTERNAL,
                    false
                );
                $partner = null;
                $extraPlayers = collect();
            }
        } catch (\RuntimeException $e) {
            $field = 'no_hp';

            if (str_contains($e->getMessage(), 'gambar') || str_contains($e->getMessage(), 'WebP') || str_contains($e->getMessage(), 'Foto')) {
                $field = 'foto';
            } elseif (str_contains($e->getMessage(), 'Nama grup')) {
                $field = 'nama_grup';
            }

            return redirect()
                ->route('guest.register.form', array_filter([
                    'no_hp' => $request->input('no_hp'),
                    'no_hp_2' => $request->input('player_2.no_hp'),
                    'no_hp_3' => $request->input('player_3.no_hp'),
                    'no_hp_4' => $request->input('player_4.no_hp'),
                    'nama_grup' => $request->input('nama_grup'),
                    'registration_mode' => $request->input('registration_mode'),
                    'id_turnamen' => $turnamen->id,
                ]))
                ->withInput()
                ->withErrors([$field => $e->getMessage()]);
        }

        $playerPayloads = [$this->playerPayload($pemain, $turnamen)];

        if ($partner) {
            $playerPayloads[] = $this->playerPayload($partner, $turnamen);
        }

        foreach ($extraPlayers ?? collect() as $extra) {
            $playerPayloads[] = $this->playerPayload($extra, $turnamen);
        }

        return redirect()
            ->route('guest.register.success')
            ->with('registration_success', [
                'is_double' => $turnamen->isDouble(),
                'is_group' => (bool) $namaGrup,
                'nama_grup' => $namaGrup,
                'individual_registration' => false,
                'paired_registration' => (bool) $partner,
                'turnamen_id' => $turnamen->id,
                'players' => $playerPayloads,
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
        $namaGrup = $registration['nama_grup'] ?? null;
        $isGroupRegistration = ! empty($registration['is_group']);

        return view('guest.register-success', compact(
            'players',
            'playerModels',
            'turnamen',
            'namaGrup',
            'isGroupRegistration'
        ));
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
