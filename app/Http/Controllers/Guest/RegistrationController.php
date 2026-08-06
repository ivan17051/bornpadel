<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPublicKategori;
use App\Http\Requests\LookupPemainRegistrationRequest;
use App\Http\Requests\StorePemainRegistrationRequest;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use App\Models\TurnamenPeserta;
use App\Services\PemainRegistrationService;
use RuntimeException;

class RegistrationController extends Controller
{
    use ResolvesPublicKategori;

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

        $turnamen->loadMissing('kategori');
        $kategori = $this->resolveRegistrationKategori($turnamen, false);

        if ($turnamen->hasMultipleKategori() && ! $kategori) {
            return view('guest.register', [
                'turnamen' => $turnamen,
                'openTournaments' => $openTournaments,
                'kategori' => null,
                'kategoriList' => $this->openKategoriList($turnamen),
                'needsKategoriSelection' => true,
            ]);
        }

        if ($kategori && ! $kategori->isRegistrationOpen()) {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                ->with('warning', 'Pendaftaran untuk kategori ini sudah ditutup.');
        }

        return view('guest.register', [
            'turnamen' => $turnamen,
            'openTournaments' => $openTournaments,
            'kategori' => $kategori,
            'kategoriList' => $this->openKategoriList($turnamen),
            'needsKategoriSelection' => false,
        ]);
    }

    public function lookup(LookupPemainRegistrationRequest $request)
    {
        $turnamen = $this->resolveRegistrationTurnamen();

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Pendaftaran ditutup. Tidak ada turnamen aktif.');
        }

        try {
            $kategori = $this->resolveRegistrationKategori($turnamen, true);
        } catch (RuntimeException $e) {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                ->withInput()
                ->withErrors(['id_kategori' => $e->getMessage()]);
        }

        if (! $kategori->isRegistrationOpen()) {
            return redirect()->route('guest.register', $this->publicTurnamenQuery($turnamen, $kategori))
                ->with('warning', 'Pendaftaran untuk kategori ini sudah ditutup.');
        }

        $validated = $request->validated();
        $noHp = trim($validated['no_hp']);
        $registrationMode = $validated['registration_mode'] ?? 'single';
        $isPairMode = $turnamen->requiresPairRegistration() && $registrationMode === 'pair';
        $isGroupMode = $turnamen->allowsGroupRegistration() && $registrationMode === 'group';
        $groupSize = $isGroupMode ? $kategori->friendlyPlayersPerGroup() : 0;
        $namaGrup = $isGroupMode ? trim($validated['nama_grup'] ?? '') : null;

        $phones = ['no_hp' => $noHp];

        if ($isPairMode || $isGroupMode) {
            $phones['no_hp_2'] = trim($validated['no_hp_2'] ?? '');
        }

        if ($isGroupMode) {
            for ($n = 3; $n <= $groupSize; $n++) {
                $phones['no_hp_' . $n] = trim($validated['no_hp_' . $n] ?? '');
            }
        }

        $phones = array_filter($phones, fn ($value) => $value !== null && $value !== '');

        foreach ($phones as $field => $phone) {
            $existing = $this->registrationService->findPemainByPhone($phone);
            if ($existing && $this->registrationService->isRegisteredForTournament($existing, $turnamen, $kategori->id)) {
                $label = $field === 'no_hp' ? 'pemain 1' : 'pemain ' . substr($field, 6);

                return back()
                    ->withInput()
                    ->withErrors([$field => 'Nomor HP ' . $label . ' sudah terdaftar pada kategori ini.']);
            }
        }

        $formParams = array_merge([
            'no_hp' => $noHp,
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $kategori->id,
            'registration_mode' => $isGroupMode ? 'group' : ($isPairMode ? 'pair' : 'single'),
        ], $isPairMode || $isGroupMode ? ['no_hp_2' => $phones['no_hp_2'] ?? ''] : []);

        if ($isGroupMode) {
            for ($n = 3; $n <= $groupSize; $n++) {
                $formParams['no_hp_' . $n] = $phones['no_hp_' . $n] ?? '';
            }
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

        try {
            $kategori = $this->resolveRegistrationKategori($turnamen, true);
        } catch (RuntimeException $e) {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                ->withErrors(['id_kategori' => $e->getMessage()]);
        }

        $noHp = trim((string) request('no_hp', old('no_hp', '')));
        $registrationMode = request('registration_mode', old('registration_mode', 'single'));
        $isPairMode = $turnamen->requiresPairRegistration() && $registrationMode === 'pair';
        $isGroupMode = $turnamen->allowsGroupRegistration() && $registrationMode === 'group';
        $groupSize = $isGroupMode ? $kategori->friendlyPlayersPerGroup() : 0;
        $namaGrup = $isGroupMode ? trim((string) request('nama_grup', old('nama_grup', ''))) : '';
        $queryBase = $this->publicTurnamenQuery($turnamen, $kategori);

        $phones = [$noHp];
        if ($isPairMode || $isGroupMode) {
            $phones[1] = trim((string) request('no_hp_2', old('player_2.no_hp', '')));
        }
        if ($isGroupMode) {
            for ($n = 3; $n <= $groupSize; $n++) {
                $phones[$n - 1] = trim((string) request(
                    'no_hp_' . $n,
                    old('player_' . $n . '.no_hp', '')
                ));
            }
        }

        if ($noHp === '') {
            return redirect()->route('guest.register', $queryBase);
        }

        if ($isPairMode && ($phones[1] ?? '') === '') {
            return redirect()->route('guest.register', $queryBase)
                ->withErrors(['no_hp_2' => 'Nomor HP pemain 2 wajib diisi untuk pendaftaran berpasangan.']);
        }

        if ($isGroupMode) {
            $missingPhone = collect($phones)->contains(fn ($phone) => $phone === '');
            if ($missingPhone || $namaGrup === '') {
                return redirect()->route('guest.register', $queryBase)
                    ->withErrors([
                        'no_hp' => "Lengkapi {$groupSize} nomor HP dan nama grup untuk pendaftaran satu grup.",
                    ]);
            }
        }

        $phoneChecks = [
            ['phone' => $noHp, 'field' => 'no_hp', 'label' => 'pemain 1'],
        ];

        if ($isPairMode || $isGroupMode) {
            $phoneChecks[] = ['phone' => $phones[1], 'field' => 'no_hp_2', 'label' => 'pemain 2'];
        }

        if ($isGroupMode) {
            for ($n = 3; $n <= $groupSize; $n++) {
                $phoneChecks[] = [
                    'phone' => $phones[$n - 1],
                    'field' => 'no_hp_' . $n,
                    'label' => 'pemain ' . $n,
                ];
            }
        }

        foreach ($phoneChecks as $check) {
            $existing = $this->registrationService->findPemainByPhone($check['phone']);
            if ($existing && $this->registrationService->isRegisteredForTournament($existing, $turnamen, $kategori->id)) {
                return redirect()->route('guest.register', $queryBase)
                    ->withErrors([$check['field'] => 'Nomor HP ' . $check['label'] . ' sudah terdaftar pada kategori ini.']);
            }
        }

        $existingPlayers = [];
        foreach ($phones as $index => $phone) {
            $existingPlayers[$index] = $phone !== ''
                ? $this->registrationService->findPemainByPhone($phone)
                : null;
        }

        return view('guest.register-form', [
            'turnamen' => $turnamen,
            'kategori' => $kategori,
            'phones' => $phones,
            'noHp' => $noHp,
            'noHp2' => $phones[1] ?? '',
            'namaGrup' => $namaGrup,
            'groupSize' => $groupSize ?: $kategori->friendlyPlayersPerGroup(),
            'existingPlayers' => $existingPlayers,
            'existingPemain' => $existingPlayers[0] ?? null,
            'isExisting' => (bool) ($existingPlayers[0] ?? null),
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

        try {
            $kategori = $this->resolveRegistrationKategori($turnamen, true);
        } catch (RuntimeException $e) {
            return redirect()->route('guest.register', ['id_turnamen' => $turnamen->id])
                ->withInput()
                ->withErrors(['id_kategori' => $e->getMessage()]);
        }

        if (! $kategori->isRegistrationOpen()) {
            return redirect()->route('guest.register', $this->publicTurnamenQuery($turnamen, $kategori))
                ->with('warning', 'Pendaftaran untuk kategori ini sudah ditutup.');
        }

        $buktiBayar = $request->file('bukti_bayar');
        $namaGrup = null;
        $kategoriId = $kategori->id;

        try {
            $registerAsGroup = $turnamen->allowsGroupRegistration() && $request->isGroupRegistration();
            $registerAsPair = $turnamen->requiresPairRegistration() && $request->isPairRegistration();

            if ($registerAsGroup) {
                $result = $this->registrationService->registerGroup(
                    $turnamen,
                    (string) $request->input('nama_grup'),
                    $request->groupPlayersPayload(),
                    $request->groupFotosPayload(),
                    $buktiBayar,
                    TurnamenPeserta::SUMBER_INTERNAL,
                    false,
                    null,
                    null,
                    $kategoriId
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
                    false,
                    null,
                    null,
                    $kategoriId
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
                    false,
                    $kategoriId
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
                ->route('guest.register.form', array_filter(array_merge([
                    'no_hp' => $request->input('no_hp'),
                    'nama_grup' => $request->input('nama_grup'),
                    'registration_mode' => $request->input('registration_mode'),
                    'id_turnamen' => $turnamen->id,
                    'id_kategori' => $kategoriId,
                ], collect(range(2, $kategori->friendlyPlayersPerGroup()))
                    ->mapWithKeys(fn ($n) => ['no_hp_' . $n => $request->input('player_' . $n . '.no_hp')])
                    ->all())))
                ->withInput()
                ->withErrors([$field => $e->getMessage()]);
        }

        $playerPayloads = [$this->playerPayload($pemain, $turnamen, $kategoriId)];

        if ($partner) {
            $playerPayloads[] = $this->playerPayload($partner, $turnamen, $kategoriId);
        }

        foreach ($extraPlayers ?? collect() as $extra) {
            $playerPayloads[] = $this->playerPayload($extra, $turnamen, $kategoriId);
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
                'kategori_id' => $kategoriId,
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
        $kategori = null;
        if ($turnamen && ! empty($registration['kategori_id'])) {
            $kategori = $turnamen->kategori()->find($registration['kategori_id']);
        }
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
            'kategori',
            'namaGrup',
            'isGroupRegistration'
        ));
    }

    protected function playerPayload(Pemain $pemain, $turnamen, $idKategori = null): array
    {
        return [
            'id' => $pemain->id,
            'nama' => $pemain->nama,
            'no_hp' => $pemain->no_hp,
            'status' => $this->registrationService->getRegistrationStatus($pemain, $turnamen, $idKategori),
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

    /**
     * @return \Illuminate\Support\Collection<int, TurnamenKategori>
     */
    protected function openKategoriList(Turnamen $turnamen)
    {
        $turnamen->loadMissing('kategori');

        return $turnamen->kategori
            ->sortBy([['urutan', 'asc'], ['id', 'asc']])
            ->values();
    }

    protected function resolveRegistrationKategori(Turnamen $turnamen, bool $requireWhenMultiple): ?TurnamenKategori
    {
        $id = $this->requestKategoriId(request())
            ?? (old('id_kategori') ? (int) old('id_kategori') : null);

        if ($turnamen->hasMultipleKategori() && $id === null) {
            if ($requireWhenMultiple) {
                throw new RuntimeException('Pilih kategori kompetisi terlebih dahulu.');
            }

            return null;
        }

        $kategori = $turnamen->resolveKategori($id);

        if ($turnamen->hasMultipleKategori() && $id !== null && (int) $kategori->id_turnamen !== (int) $turnamen->id) {
            throw new RuntimeException('Kategori tidak valid untuk turnamen ini.');
        }

        return $kategori;
    }
}
