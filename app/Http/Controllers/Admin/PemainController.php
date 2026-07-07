<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LookupPartnerPemainRequest;
use App\Http\Requests\Admin\LookupPemainRequest;
use App\Http\Requests\Admin\StorePartnerPemainRequest;
use App\Http\Requests\Admin\StorePemainRequest;
use App\Http\Requests\Admin\UpdatePemainRequest;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\PemainPhotoService;
use App\Services\PemainRegistrationService;
use App\Services\TournamentAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PemainController extends Controller
{
    protected $matchmakingService;
    protected $photoService;
    protected $tournamentAccess;
    protected $registrationService;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        PemainPhotoService $photoService,
        TournamentAccessService $tournamentAccess,
        PemainRegistrationService $registrationService
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->photoService = $photoService;
        $this->tournamentAccess = $tournamentAccess;
        $this->registrationService = $registrationService;
    }

    public function index(Request $request)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $turnamen = $this->matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );

        $isDoubleView = $turnamen && $turnamen->isDouble() && $turnamen->isRegistrationClosed();

        if (! $turnamen) {
            $peserta = null;
            $pemain = Pemain::query()->whereRaw('0 = 1')->paginate(15)->withQueryString();
            $isDoubleView = false;

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $pemain,
                ]);
            }

            return view('admin.pemain.index', compact('peserta', 'pemain', 'turnamen', 'turnamenList', 'isDoubleView'));
        }

        if ($isDoubleView) {
            $pesertaQuery = TurnamenPeserta::query()
                ->forTurnamen($turnamen->id)
                ->with(['pemain1', 'pemain2'])
                ->latest();

            if ($request->filled('status')) {
                $pesertaQuery->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $pesertaQuery->where(function ($builder) use ($search) {
                    $builder->whereHas('pemain1', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%");
                    })->orWhereHas('pemain2', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%");
                    });
                });
            }

            $peserta = $pesertaQuery->paginate(15)->withQueryString();
            $pemain = null;

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $peserta,
                ]);
            }

            return view('admin.pemain.index', compact('peserta', 'pemain', 'turnamen', 'turnamenList', 'isDoubleView'));
        }

        $query = Pemain::query()->latest();

        if ($turnamen) {
            $query->where(function ($builder) use ($turnamen, $request) {
                $builder->whereHas('turnamenPesertaAsPemain1', function ($q) use ($turnamen, $request) {
                    $q->where('id_turnamen', $turnamen->id);
                    if ($request->filled('status')) {
                        $q->where('status', $request->status);
                    }
                })->orWhereHas('turnamenPesertaAsPemain2', function ($q) use ($turnamen, $request) {
                    $q->where('id_turnamen', $turnamen->id);
                    if ($request->filled('status')) {
                        $q->where('status', $request->status);
                    }
                });
            });
        } elseif ($request->filled('status')) {
            $query->where(function ($builder) use ($request) {
                $builder->whereHas('turnamenPesertaAsPemain1', function ($q) use ($request) {
                    $q->where('status', $request->status);
                })->orWhereHas('turnamenPesertaAsPemain2', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        $pemain = $query->paginate(15)->withQueryString();
        $peserta = null;
        $isDoubleView = false;

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $pemain,
            ]);
        }

        return view('admin.pemain.index', compact('pemain', 'peserta', 'turnamen', 'turnamenList', 'isDoubleView'));
    }

    public function directory(Request $request)
    {
        $query = Pemain::query()
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->registration === 'none') {
            $query->withoutRegistration();
        } elseif ($request->registration === 'registered') {
            $query->withRegistration();
        }

        $pemain = $query->paginate(20)->withQueryString();
        $totalPemain = Pemain::count();
        $unregisteredCount = Pemain::withoutRegistration()->count();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $pemain,
            ]);
        }

        return view('admin.pemain.directory', compact('pemain', 'totalPemain', 'unregisteredCount'));
    }

    public function create(Request $request)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $noHp = trim((string) $request->get('no_hp', old('no_hp', '')));
        $selectedTurnamen = $request->filled('id_turnamen')
            ? Turnamen::find($request->id_turnamen)
            : null;
        $showForm = $noHp !== '' && $selectedTurnamen;

        $existingPemain = null;

        if ($showForm) {
            $existingPemain = Pemain::where('no_hp', $noHp)->first();

            if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $selectedTurnamen)) {
                return redirect()->route('admin.pemain.create', $request->only('id_turnamen'))
                    ->withInput()
                    ->withErrors(['no_hp' => 'Pemain sudah terdaftar pada turnamen ini.']);
            }
        }

        return view('admin.pemain.create', compact(
            'turnamenList',
            'selectedTurnamen',
            'showForm',
            'noHp',
            'existingPemain'
        ));
    }

    public function lookup(LookupPemainRequest $request)
    {
        $turnamen = Turnamen::findOrFail($request->id_turnamen);
        $this->tournamentAccess->assertTurnamenId((int) $turnamen->id);

        $noHp = trim($request->no_hp);
        $params = [
            'id_turnamen' => $turnamen->id,
            'no_hp' => $noHp,
            'status' => $request->input('status', 'approved'),
        ];

        $existingPemain = Pemain::where('no_hp', $noHp)->first();

        if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $turnamen)) {
            return back()
                ->withInput()
                ->withErrors(['no_hp' => 'Pemain sudah terdaftar pada turnamen ini.']);
        }

        return redirect()->route('admin.pemain.create', $params);
    }

    public function store(StorePemainRequest $request)
    {
        $data = $request->validated();
        $turnamen = Turnamen::findOrFail($data['id_turnamen']);
        $this->tournamentAccess->assertTurnamenId((int) $turnamen->id);
        $status = $data['status'];
        $buktiBayar = $request->file('bukti_bayar');

        try {
            $pemain = $this->registrationService->upsertPemain([
                'no_hp' => $data['no_hp'],
                'nama' => $data['nama'],
                'tgl_lahir' => $data['tgl_lahir'] ?? null,
                'gender' => $data['gender'],
                'rating' => $data['rating'] ?? null,
            ], $request->file('foto'));

            if ($this->registrationService->isRegisteredForTournament($pemain, $turnamen)) {
                throw new \RuntimeException('Pemain sudah terdaftar pada turnamen ini.');
            }

            TurnamenPeserta::create([
                'id_turnamen' => $turnamen->id,
                'id_pemain1' => $pemain->id,
                'status' => $status,
                'bukti_bayar' => $this->registrationService->storeBuktiBayar($buktiBayar),
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['no_hp' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.pemain.index', ['id_turnamen' => $turnamen->id])
            ->with('success', 'Pemain berhasil ditambahkan.');
    }

    protected function otherPemainForSlot(TurnamenPeserta $peserta, int $slot): ?Pemain
    {
        return $slot === 1 ? $peserta->pemain2 : $peserta->pemain1;
    }

    protected function pesertaSlotField(int $slot): string
    {
        return $slot === 1 ? 'id_pemain1' : 'id_pemain2';
    }

    protected function redirectIfPesertaSlotUnavailable(TurnamenPeserta $peserta, int $slot)
    {
        $peserta->loadMissing(['turnamen', 'pemain1', 'pemain2']);
        $this->tournamentAccess->assertTurnamenId((int) $peserta->id_turnamen);

        if (! $peserta->turnamen->isDouble()) {
            return redirect()
                ->route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen])
                ->with('error', 'Turnamen ini bukan kategori double.');
        }

        if ($peserta->{$this->pesertaSlotField($slot)}) {
            return redirect()
                ->route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen])
                ->with('error', 'Pemain ' . $slot . ' sudah terisi pada pasangan ini.');
        }

        return null;
    }

    public function createPesertaSlot(Request $request, TurnamenPeserta $peserta, int $slot)
    {
        if ($redirect = $this->redirectIfPesertaSlotUnavailable($peserta, $slot)) {
            return $redirect;
        }

        $noHp = trim((string) $request->get('no_hp', old('no_hp', '')));
        $showForm = $noHp !== '';
        $otherPemain = $this->otherPemainForSlot($peserta, $slot);
        $existingPemain = $showForm ? Pemain::where('no_hp', $noHp)->first() : null;

        if ($showForm) {
            if ($otherPemain && $otherPemain->no_hp === $noHp) {
                return redirect()
                    ->route('admin.pemain.peserta.slot.create', ['peserta' => $peserta->id, 'slot' => $slot])
                    ->withInput()
                    ->withErrors(['no_hp' => 'Nomor HP pemain ' . $slot . ' harus berbeda dari pemain ' . ($slot === 1 ? 2 : 1) . '.']);
            }

            if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $peserta->turnamen)) {
                return redirect()
                    ->route('admin.pemain.peserta.slot.create', ['peserta' => $peserta->id, 'slot' => $slot])
                    ->withInput()
                    ->withErrors(['no_hp' => 'Pemain ' . $slot . ' sudah terdaftar pada turnamen ini.']);
            }
        }

        return view('admin.pemain.add-peserta-slot', compact(
            'peserta',
            'slot',
            'showForm',
            'noHp',
            'existingPemain',
            'otherPemain'
        ));
    }

    public function lookupPesertaSlot(LookupPartnerPemainRequest $request, TurnamenPeserta $peserta, int $slot)
    {
        if ($redirect = $this->redirectIfPesertaSlotUnavailable($peserta, $slot)) {
            return $redirect;
        }

        $noHp = trim($request->no_hp);
        $otherPemain = $this->otherPemainForSlot($peserta, $slot);

        if ($otherPemain && $otherPemain->no_hp === $noHp) {
            return back()->withInput()->withErrors([
                'no_hp' => 'Nomor HP pemain ' . $slot . ' harus berbeda dari pemain ' . ($slot === 1 ? 2 : 1) . '.',
            ]);
        }

        $existingPemain = Pemain::where('no_hp', $noHp)->first();

        if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $peserta->turnamen)) {
            return back()
                ->withInput()
                ->withErrors(['no_hp' => 'Pemain ' . $slot . ' sudah terdaftar pada turnamen ini.']);
        }

        return redirect()->route('admin.pemain.peserta.slot.create', [
            'peserta' => $peserta->id,
            'slot' => $slot,
            'no_hp' => $noHp,
        ]);
    }

    public function storePesertaSlot(StorePartnerPemainRequest $request, TurnamenPeserta $peserta, int $slot)
    {
        if ($redirect = $this->redirectIfPesertaSlotUnavailable($peserta, $slot)) {
            return $redirect;
        }

        $data = $request->validated();
        $otherPemain = $this->otherPemainForSlot($peserta, $slot);

        if ($otherPemain && $otherPemain->no_hp === trim($data['no_hp'])) {
            return back()->withInput()->withErrors([
                'no_hp' => 'Nomor HP pemain ' . $slot . ' harus berbeda dari pemain ' . ($slot === 1 ? 2 : 1) . '.',
            ]);
        }

        try {
            $pemain = $this->registrationService->upsertPemain([
                'no_hp' => $data['no_hp'],
                'nama' => $data['nama'],
                'tgl_lahir' => $data['tgl_lahir'] ?? null,
                'gender' => $data['gender'],
                'rating' => $data['rating'] ?? null,
            ], $request->file('foto'));

            if ($this->registrationService->isRegisteredForTournament($pemain, $peserta->turnamen)) {
                throw new \RuntimeException('Pemain ' . $slot . ' sudah terdaftar pada turnamen ini.');
            }

            $peserta->update([$this->pesertaSlotField($slot) => $pemain->id]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['no_hp' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen])
            ->with('success', 'Pemain ' . $slot . ' berhasil ditambahkan ke pasangan.');
    }

    public function createPartner(Request $request, TurnamenPeserta $peserta)
    {
        return $this->createPesertaSlot($request, $peserta, 2);
    }

    public function partnerLookup(LookupPartnerPemainRequest $request, TurnamenPeserta $peserta)
    {
        return $this->lookupPesertaSlot($request, $peserta, 2);
    }

    public function storePartner(StorePartnerPemainRequest $request, TurnamenPeserta $peserta)
    {
        return $this->storePesertaSlot($request, $peserta, 2);
    }

    public function edit(Request $request, Pemain $pemain)
    {
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $turnamenPesertaEntries = TurnamenPeserta::involvingPemain($pemain->id)
            ->with('turnamen', 'pemain1', 'pemain2')
            ->get();

        $returnQuery = $this->pemainEditReturnQuery($request);

        return view('admin.pemain.edit', compact(
            'pemain',
            'turnamenPesertaEntries',
            'returnQuery'
        ));
    }

    public function update(UpdatePemainRequest $request, Pemain $pemain)
    {
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $data = $request->validated();

        if (array_key_exists('tgl_lahir', $data)) {
            if (! empty($data['tgl_lahir'])) {
                $data['usia'] = Carbon::parse($data['tgl_lahir'])->age;
            } else {
                $data['tgl_lahir'] = null;
                $data['usia'] = null;
            }
        }

        $foto = $request->file('foto');
        unset($data['foto']);

        if ($foto) {
            try {
                $this->photoService->delete($pemain->foto);
                $data['foto'] = $this->photoService->storeAsWebp($foto);
            } catch (\RuntimeException $e) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 422);
                }

                return back()->withInput()->withErrors(['foto' => $e->getMessage()]);
            }
        }

        $pemain->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data pemain berhasil diperbarui.',
                'data' => $pemain->fresh(),
            ]);
        }

        return redirect()
            ->to($this->pemainReturnUrl($request))
            ->with('success', 'Data pemain berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Pemain $pemain)
    {
        $request->validate([
            'status' => ['required', 'in:approved,rejected,pending,unpaid,paid'],
            'id_turnamen' => ['required', 'exists:m_turnamen,id'],
            'bukti_bayar' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ]);

        $this->tournamentAccess->assertTurnamenId((int) $request->id_turnamen);
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $peserta = TurnamenPeserta::query()
            ->forTurnamen((int) $request->id_turnamen)
            ->involvingPemain($pemain->id)
            ->with('turnamen')
            ->first();

        if (! $peserta) {
            return response()->json([
                'success' => false,
                'message' => 'Pemain tidak terdaftar pada turnamen ini.',
            ], 422);
        }

        if ($request->hasFile('bukti_bayar')) {
            $this->registrationService->updateBuktiBayar($peserta, $request->file('bukti_bayar'));
            $peserta->refresh();
        }

        $peserta->update(['status' => $request->status]);

        $isPairEntry = $peserta->turnamen && $peserta->turnamen->isDouble() && $peserta->isCompletePair();

        $messages = [
            'approved' => $isPairEntry ? 'Pasangan berhasil disetujui.' : 'Pemain berhasil disetujui.',
            'rejected' => $isPairEntry ? 'Pasangan ditolak.' : 'Pemain ditolak.',
            'pending' => $isPairEntry ? 'Status pasangan dikembalikan ke pending.' : 'Status pemain dikembalikan ke pending.',
            'unpaid' => $isPairEntry ? 'Status pasangan diubah menjadi unpaid.' : 'Status pemain diubah menjadi unpaid.',
            'paid' => $isPairEntry ? 'Status pasangan diubah menjadi paid.' : 'Status pemain diubah menjadi paid.',
        ];

        return response()->json([
            'success' => true,
            'message' => $messages[$request->status],
            'data' => $peserta->fresh(),
        ]);
    }

    public function detachFromTurnamen(Request $request, Pemain $pemain)
    {
        $request->validate([
            'id_turnamen' => ['required', 'exists:m_turnamen,id'],
        ]);

        $turnamenId = (int) $request->id_turnamen;
        $this->tournamentAccess->assertTurnamenId($turnamenId);
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $peserta = TurnamenPeserta::query()
            ->forTurnamen($turnamenId)
            ->involvingPemain($pemain->id)
            ->first();

        if (! $peserta) {
            return response()->json([
                'success' => false,
                'message' => 'Pemain tidak terdaftar pada turnamen ini.',
            ], 422);
        }

        $inMatches = Pertandingan::query()
            ->where('id_turnamen', $turnamenId)
            ->where(function ($query) use ($pemain, $peserta) {
                $query->where('id_pemain1', $pemain->id)
                    ->orWhere('id_pemain2', $pemain->id)
                    ->orWhere('id_pemenang', $pemain->id)
                    ->orWhere('id_peserta1', $peserta->id)
                    ->orWhere('id_peserta2', $peserta->id)
                    ->orWhere('id_peserta_pemenang', $peserta->id);
            })
            ->exists();

        if ($inMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Pemain tidak dapat dihapus dari turnamen karena sudah terdaftar dalam pertandingan.',
            ], 422);
        }

        $this->registrationService->detachPemainFromPeserta($peserta, $pemain->id);

        return response()->json([
            'success' => true,
            'message' => 'Pemain berhasil dihapus dari turnamen.',
        ]);
    }

    public function destroy(Pemain $pemain)
    {
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $pesertaQuery = TurnamenPeserta::query()->involvingPemain($pemain->id);

        if ($this->tournamentAccess->isPanitia()) {
            $pesertaQuery->forTurnamen($this->tournamentAccess->assignedTurnamenId());
        }

        $pesertaIds = $pesertaQuery->pluck('id');

        $matchQuery = Pertandingan::where(function ($q) use ($pemain, $pesertaIds) {
            $q->where('id_pemain1', $pemain->id)
                ->orWhere('id_pemain2', $pemain->id)
                ->orWhere('id_pemenang', $pemain->id);

            if ($pesertaIds->isNotEmpty()) {
                $q->orWhereIn('id_peserta1', $pesertaIds)
                    ->orWhereIn('id_peserta2', $pesertaIds)
                    ->orWhereIn('id_peserta_pemenang', $pesertaIds);
            }
        });

        if ($this->tournamentAccess->isPanitia()) {
            $matchQuery->where('id_turnamen', $this->tournamentAccess->assignedTurnamenId());
        }

        $inMatches = $matchQuery->exists();

        if ($inMatches) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pemain tidak dapat dihapus karena sudah terdaftar dalam pertandingan.',
                ], 422);
            }

            return back()->with('error', 'Pemain tidak dapat dihapus karena sudah terdaftar dalam pertandingan.');
        }

        DB::transaction(function () use ($pemain, $pesertaQuery) {
            $pesertaQuery->with('turnamen')->get()->each(function (TurnamenPeserta $peserta) use ($pemain) {
                if ($peserta->turnamen && $peserta->turnamen->isDouble()) {
                    $this->registrationService->detachPemainFromPeserta($peserta, $pemain->id);
                }
            });

            $this->photoService->delete($pemain->foto);
            $pemain->delete();
        });

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Profil pemain berhasil dihapus.',
            ]);
        }

        return redirect()
            ->to($this->pemainReturnUrl($request))
            ->with('success', 'Profil pemain berhasil dihapus.');
    }

    protected function pemainEditReturnQuery(Request $request): array
    {
        $from = $request->input('from', 'index');

        if ($from === 'directory') {
            return array_filter(array_merge(
                ['from' => 'directory'],
                $request->only(['search', 'gender', 'registration', 'page'])
            ), function ($value) {
                return $value !== null && $value !== '';
            });
        }

        if ($from === 'dashboard') {
            return ['from' => 'dashboard'];
        }

        if ($from === 'turnamen-operasi') {
            return array_filter(array_merge(
                ['from' => 'turnamen-operasi', 'tab' => 'pemain'],
                $request->only(['id_turnamen', 'search', 'status'])
            ), function ($value) {
                return $value !== null && $value !== '';
            });
        }

        return array_filter(array_merge(
            ['from' => 'index'],
            $request->only(['id_turnamen', 'search', 'status'])
        ), function ($value) {
            return $value !== null && $value !== '';
        });
    }

    protected function pemainReturnUrl(Request $request): string
    {
        $from = $request->input('from', 'index');

        if ($from === 'directory') {
            return route('admin.pemain.directory', $request->only(['search', 'gender', 'registration', 'page']));
        }

        if ($from === 'dashboard') {
            return route('admin.dashboard');
        }

        if ($from === 'turnamen-operasi') {
            return route('admin.turnamen-operasi.index', array_merge(
                ['tab' => 'pemain'],
                $request->only(['id_turnamen', 'search', 'status'])
            ));
        }

        return route('admin.pemain.index', $request->only(['id_turnamen', 'search', 'status']));
    }
}
