<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PemainRegistrationService
{
    protected $photoService;
    protected $paymentReceiptService;

    public function __construct(PemainPhotoService $photoService, PaymentReceiptService $paymentReceiptService)
    {
        $this->photoService = $photoService;
        $this->paymentReceiptService = $paymentReceiptService;
    }

    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::open()->latest('doc')->first();
    }

    public function resolveOpenTournament(?int $turnamenId = null): ?Turnamen
    {
        if ($turnamenId) {
            return Turnamen::open()->where('id', $turnamenId)->first();
        }

        return $this->getActiveTournament();
    }

    public function getOpenTournaments(): Collection
    {
        return Turnamen::open()->latest('doc')->get();
    }

    public function getPublicActiveTournaments(): Collection
    {
        return $this->publicTournamentQuery()
            ->publicActive()
            ->get();
    }

    public function getPublicCompletedTournaments(int $month, int $year): Collection
    {
        $month = max(1, min(12, $month));
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return $this->publicTournamentQuery()
            ->publicCompleted()
            ->whereBetween('tanggal', [$start, $end])
            ->get();
    }

    /**
     * @return array{month: int, year: int, years: array<int, int>, hasAny: bool}
     */
    public function resolvePublicCompletedFilter(?int $month, ?int $year): array
    {
        $years = $this->getPublicCompletedYearOptions();
        $hasAny = $years !== [];

        if (! $hasAny) {
            return [
                'month' => now()->month,
                'year' => now()->year,
                'years' => [],
                'hasAny' => false,
            ];
        }

        $selectedYear = ($year && in_array($year, $years, true)) ? $year : $years[0];
        $availableMonths = $this->getPublicCompletedMonthsForYear($selectedYear);

        if ($month && $year && in_array($year, $years, true) && in_array($month, $availableMonths, true)) {
            $selectedMonth = $month;
            $selectedYear = $year;
        } else {
            $selectedMonth = $availableMonths[0] ?? now()->month;
        }

        return [
            'month' => $selectedMonth,
            'year' => $selectedYear,
            'years' => $years,
            'hasAny' => true,
        ];
    }

    public function monthLabel(int $month): string
    {
        return Carbon::createFromDate(2000, $month, 1)
            ->locale('id')
            ->translatedFormat('F');
    }

    protected function publicTournamentQuery()
    {
        return Turnamen::query()->withCount([
            'turnamenPeserta as registered_count' => function ($query) {
                $query->where('status', '!=', 'rejected');
            },
            'turnamenPeserta as approved_count' => function ($query) {
                $query->where('status', 'approved');
            },
        ]);
    }

    protected function getPublicCompletedYearOptions(): array
    {
        $cutoff = now()->subYear()->startOfDay();

        return Turnamen::query()
            ->where('status', 'completed')
            ->where('tanggal', '>=', $cutoff)
            ->selectRaw('YEAR(tanggal) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();
    }

    public function getPublicCompletedMonthsForYear(int $year): array
    {
        $cutoff = now()->subYear()->startOfDay();
        $start = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $end = Carbon::createFromDate($year, 12, 31)->endOfDay();

        return Turnamen::query()
            ->where('status', 'completed')
            ->where('tanggal', '>=', $cutoff)
            ->whereBetween('tanggal', [$start, $end])
            ->selectRaw('MONTH(tanggal) as month')
            ->distinct()
            ->orderByDesc('month')
            ->pluck('month')
            ->map(fn ($month) => (int) $month)
            ->values()
            ->all();
    }

    public function getPublicTournaments(): Collection
    {
        return $this->getPublicActiveTournaments();
    }

    public function resolvePublicTournament(?int $turnamenId = null): ?Turnamen
    {
        if ($turnamenId) {
            $cutoff = now()->subYear()->startOfDay();

            return Turnamen::query()
                ->where('id', $turnamenId)
                ->where(function ($builder) use ($cutoff) {
                    $builder->whereIn('status', ['open', 'ongoing'])
                        ->orWhere(function ($completed) use ($cutoff) {
                            $completed->where('status', 'completed')
                                ->where('tanggal', '>=', $cutoff);
                        });
                })
                ->first();
        }

        return Turnamen::publicActive()->orderByDesc('tanggal')->first();
    }

    public function findPemainByPhone(string $noHp): ?Pemain
    {
        $trimmed = trim($noHp);

        return Pemain::where('no_hp', $trimmed)->first();
    }

    public function isRegisteredForTournament(Pemain $pemain, Turnamen $turnamen): bool
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->involvingPemain($pemain->id)
            ->exists();
    }

    /**
     * Pemain master who are not yet registered on this tournament.
     */
    public function availablePemainQuery(Turnamen $turnamen)
    {
        return Pemain::query()
            ->whereNotIn('id', function ($query) use ($turnamen) {
                $query->select('id_pemain1')
                    ->from('turnamen_peserta')
                    ->where('id_turnamen', $turnamen->id);
            });
    }

    /**
     * Register existing pemain into a tournament (single rows; doubles pairing is separate).
     *
     * @param  array<int, int>  $pemainIds
     * @return array{registered: \Illuminate\Support\Collection<int, TurnamenPeserta>, skipped: array<int, int>, skipped_count: int}
     */
    public function bulkRegisterExisting(
        Turnamen $turnamen,
        array $pemainIds,
        string $status = 'approved',
        ?TournamentCapacityService $capacityService = null
    ): array {
        $pemainIds = array_values(array_unique(array_map('intval', $pemainIds)));

        if ($pemainIds === []) {
            throw new RuntimeException('Pilih minimal satu pemain.');
        }

        $players = Pemain::query()->whereIn('id', $pemainIds)->get()->keyBy('id');
        $missing = array_diff($pemainIds, $players->keys()->all());

        if ($missing !== []) {
            throw new RuntimeException('Beberapa pemain tidak ditemukan.');
        }

        $toRegister = [];
        $skipped = [];

        foreach ($pemainIds as $pemainId) {
            $pemain = $players->get($pemainId);

            if ($this->isRegisteredForTournament($pemain, $turnamen)) {
                $skipped[] = $pemainId;
                continue;
            }

            $toRegister[] = $pemain;
        }

        if ($toRegister === []) {
            return [
                'registered' => collect(),
                'skipped' => $skipped,
                'skipped_count' => count($skipped),
            ];
        }

        $capacityService = $capacityService ?? app(TournamentCapacityService::class);

        if ($status === 'approved') {
            $capacityService->assertCanApprove($turnamen, count($toRegister));
        }

        $registered = collect();

        foreach ($toRegister as $pemain) {
            $registered->push(TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => $status,
                'bukti_bayar' => null,
                'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
            ]));
        }

        return [
            'registered' => $registered,
            'skipped' => $skipped,
            'skipped_count' => count($skipped),
        ];
    }

    /**
     * Create a brand-new pemain profile and register them to the tournament.
     */
    public function createNewAndRegister(
        Turnamen $turnamen,
        array $data,
        string $status = 'approved',
        ?UploadedFile $foto = null,
        ?UploadedFile $buktiBayar = null,
        ?TournamentCapacityService $capacityService = null
    ): Pemain {
        if ($this->findPemainByPhone($data['no_hp'])) {
            throw new RuntimeException(
                'Nomor HP sudah ada di database. Pilih pemain dari tabel untuk mendaftarkan profil existing.'
            );
        }

        $capacityService = $capacityService ?? app(TournamentCapacityService::class);

        if ($status === 'approved') {
            $capacityService->assertCanApprove($turnamen, 1);
        }

        $pemain = $this->upsertPemain($data, $foto, true);

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => $status,
            'bukti_bayar' => $this->storeBuktiBayar($buktiBayar),
            'sumber' => TurnamenPeserta::SUMBER_INTERNAL,
        ]);

        return $pemain->fresh();
    }

    /**
     * @return array{type: string, items: Collection}
     */
    public function getPublicParticipantList(Turnamen $turnamen): array
    {
        if ($turnamen->playsAsPairs() && $turnamen->isRegistrationClosed()) {
            $items = TurnamenPeserta::query()
                ->forTurnamen($turnamen->id)
                ->whereHas('pasanganAsPeserta1')
                ->with(['pemain1', 'pasanganAsPeserta1.peserta2.pemain1'])
                ->orderBy('id')
                ->get()
                ->map(function (TurnamenPeserta $entry) {
                    $partner = optional($entry->pasanganAsPeserta1)->peserta2;

                    return [
                        'label' => $entry->display_name,
                        'pemain1' => optional($entry->pemain1)->nama,
                        'pemain2' => optional(optional($partner)->pemain1)->nama,
                        'status' => $entry->status,
                    ];
                });

            return ['type' => 'pairs', 'items' => $items];
        }

        $items = TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->where('status', '!=', 'rejected')
            ->with(['pemain1', 'pasanganAsPeserta1.peserta2.pemain1', 'pasanganAsPeserta2.peserta1.pemain1'])
            ->orderBy('id')
            ->get()
            ->map(function (TurnamenPeserta $peserta) use ($turnamen) {
                $partnerName = optional($peserta->partner_pemain)->nama;

                return [
                    'id' => $peserta->id,
                    'nama' => optional($peserta->pemain1)->nama ?? '-',
                    'partner' => $partnerName,
                    'display' => $partnerName
                        ? trim((optional($peserta->pemain1)->nama ?? '') . ' / ' . $partnerName)
                        : (optional($peserta->pemain1)->nama ?? '-'),
                    'status' => $peserta->status,
                    'is_paired' => $peserta->isPaired(),
                ];
            });

        return [
            'type' => $turnamen->requiresPairRegistration() ? 'double_individual' : 'single',
            'items' => $items,
        ];
    }

    public function getSoloPesertaOptions(Turnamen $turnamen): Collection
    {
        if (! $turnamen->requiresPairRegistration()) {
            return collect();
        }

        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->soloEntries()
            ->with('pemain1')
            ->orderBy('id')
            ->get();
    }

    public function register(
        Turnamen $turnamen,
        array $data,
        ?UploadedFile $foto = null,
        ?UploadedFile $buktiBayar = null,
        string $sumber = TurnamenPeserta::SUMBER_INTERNAL,
        bool $updateExistingProfile = true
    ): Pemain {
        $pemain = $this->upsertPemain($data, $foto, $updateExistingProfile);

        if ($this->isRegisteredForTournament($pemain, $turnamen)) {
            throw new RuntimeException('Nomor HP sudah terdaftar pada turnamen ini.');
        }

        $buktiPath = $this->storeBuktiBayar($buktiBayar);

        TurnamenPeserta::create([
            'id_turnamen' => $turnamen->id,
            'id_pemain1' => $pemain->id,
            'status' => $this->resolveRegistrationStatusFromBukti($buktiPath),
            'bukti_bayar' => $buktiPath,
            'sumber' => $sumber,
        ]);

        return $pemain->fresh();
    }

    /**
     * @return array{pemain: Pemain, partner: Pemain}
     */
    public function registerPair(
        Turnamen $turnamen,
        array $player1,
        ?UploadedFile $foto1,
        array $player2,
        ?UploadedFile $foto2,
        ?UploadedFile $buktiBayar = null,
        string $sumber = TurnamenPeserta::SUMBER_INTERNAL,
        bool $updateExistingProfile = true,
        ?string $statusOverride = null,
        ?TournamentCapacityService $capacityService = null
    ): array {
        if (! $turnamen->requiresPairRegistration()) {
            throw new RuntimeException('Pendaftaran berpasangan hanya tersedia untuk turnamen double.');
        }

        if (trim($player1['no_hp']) === trim($player2['no_hp'])) {
            throw new RuntimeException('Nomor HP pemain 1 dan pemain 2 tidak boleh sama.');
        }

        $existingPlayer1 = $this->findPemainByPhone($player1['no_hp']);
        $existingPlayer2 = $this->findPemainByPhone($player2['no_hp']);

        if ($existingPlayer1 && $this->isRegisteredForTournament($existingPlayer1, $turnamen)) {
            throw new RuntimeException('Nomor HP pemain 1 sudah terdaftar pada turnamen ini.');
        }

        if ($existingPlayer2 && $this->isRegisteredForTournament($existingPlayer2, $turnamen)) {
            throw new RuntimeException('Nomor HP pemain 2 sudah terdaftar pada turnamen ini.');
        }

        $status = $statusOverride ?: $this->resolveRegistrationStatusFromBukti(null, $buktiBayar);
        $capacityService = $capacityService ?? app(TournamentCapacityService::class);

        if ($status === 'approved') {
            $capacityService->assertCanApprove($turnamen, 2);
        }

        return DB::transaction(function () use (
            $turnamen,
            $player1,
            $foto1,
            $player2,
            $foto2,
            $buktiBayar,
            $sumber,
            $updateExistingProfile,
            $status
        ) {
            $pemain = $this->upsertPemain($player1, $foto1, $updateExistingProfile);
            $partner = $this->upsertPemain($player2, $foto2, $updateExistingProfile);
            $buktiPath = $this->storeBuktiBayar($buktiBayar);

            $peserta1 = TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => $status,
                'bukti_bayar' => $buktiPath,
                'sumber' => $sumber,
            ]);

            $peserta2 = TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $partner->id,
                'status' => $status,
                'bukti_bayar' => $buktiPath,
                'sumber' => $sumber,
            ]);

            app(DoublePairingService::class)->createPair($turnamen, $peserta1, $peserta2);

            return [
                'pemain' => $pemain,
                'partner' => $partner,
            ];
        });
    }

    public function upsertPemain(array $data, ?UploadedFile $foto = null, bool $updateExisting = true): Pemain
    {
        $existing = $this->findPemainByPhone($data['no_hp']);

        if ($existing) {
            if (! $updateExisting) {
                return $existing;
            }

            $updatePayload = array_merge([
                'nama' => $data['nama'],
                'gender' => $data['gender'],
                'rating' => $data['rating'] ?? $existing->rating ?? 0,
            ], $this->resolveBirthFields($data));

            if ($foto) {
                $this->photoService->delete($existing->foto);
                $updatePayload['foto'] = $this->photoService->storeAsWebp($foto);
            }

            $existing->update($updatePayload);

            return $existing->fresh();
        }

        $fotoPath = $foto ? $this->photoService->storeAsWebp($foto) : null;

        return Pemain::create(array_merge([
            'nama' => $data['nama'],
            'gender' => $data['gender'],
            'no_hp' => trim($data['no_hp']),
            'rating' => $data['rating'] ?? 0,
            'foto' => $fotoPath,
        ], $this->resolveBirthFields($data)));
    }

    protected function resolveBirthFields(array $data): array
    {
        $tglLahir = $data['tgl_lahir'] ?? null;

        if (! empty($tglLahir)) {
            return [
                'tgl_lahir' => $tglLahir,
                'usia' => Carbon::parse($tglLahir)->age,
            ];
        }

        return [
            'tgl_lahir' => null,
            'usia' => null,
        ];
    }

    public function getRegistrationStatus(Pemain $pemain, Turnamen $turnamen): ?string
    {
        return optional($pemain->pesertaForTurnamen($turnamen))->status;
    }

    public function detachPemainFromPeserta(TurnamenPeserta $peserta, int $pemainId): void
    {
        if (! $peserta->involvesPemain($pemainId)) {
            return;
        }

        $pasangan = $peserta->pasangan;

        if ($pasangan) {
            $partnerPesertaId = (int) $pasangan->id_peserta_1 === (int) $peserta->id
                ? (int) $pasangan->id_peserta_2
                : (int) $pasangan->id_peserta_1;

            $pasangan->delete();

            if ((int) $peserta->id_pemain1 === $pemainId) {
                $peserta->delete();

                return;
            }

            $peserta = TurnamenPeserta::find($partnerPesertaId);

            if (! $peserta) {
                return;
            }
        }

        if ((int) $peserta->id_pemain1 !== $pemainId) {
            return;
        }

        $peserta->delete();
    }

    public function resolveRegistrationStatusFromBukti(?string $buktiBayarPath = null, ?UploadedFile $buktiBayar = null): string
    {
        if ($buktiBayar || $buktiBayarPath) {
            return 'paid';
        }

        return 'unpaid';
    }

    public function storeBuktiBayar(?UploadedFile $buktiBayar): ?string
    {
        if (! $buktiBayar) {
            return null;
        }

        return $this->paymentReceiptService->store($buktiBayar);
    }

    public function updateBuktiBayar(TurnamenPeserta $peserta, ?UploadedFile $buktiBayar): void
    {
        if (! $buktiBayar) {
            return;
        }

        $this->paymentReceiptService->delete($peserta->bukti_bayar);
        $updates = [
            'bukti_bayar' => $this->paymentReceiptService->store($buktiBayar),
        ];

        if (in_array($peserta->status, ['unpaid', 'pending'], true)) {
            $updates['status'] = 'paid';
        }

        $peserta->update($updates);
    }
}
