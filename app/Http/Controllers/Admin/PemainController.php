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
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        $isDoubleView = $turnamen && $turnamen->isDouble();

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

    public function create(Request $request)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $noHp = trim((string) $request->get('no_hp', old('no_hp', '')));
        $partnerNoHp = trim((string) $request->get('partner_no_hp', old('partner_no_hp', '')));
        $selectedTurnamen = $request->filled('id_turnamen')
            ? Turnamen::find($request->id_turnamen)
            : null;
        $showForm = $noHp !== '' && $selectedTurnamen;

        if ($showForm && $selectedTurnamen->isDouble() && $partnerNoHp === '') {
            return redirect()->route('admin.pemain.create', $request->only('id_turnamen'));
        }

        $existingPemain = null;
        $existingPartner = null;
        $isPartnerExisting = false;

        if ($showForm) {
            $existingPemain = Pemain::where('no_hp', $noHp)->first();

            if ($selectedTurnamen->isDouble()) {
                if ($noHp === $partnerNoHp) {
                    return redirect()->route('admin.pemain.create', $request->only('id_turnamen'))
                        ->withErrors(['partner_no_hp' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.']);
                }

                $existingPartner = Pemain::where('no_hp', $partnerNoHp)->first();
                $isPartnerExisting = (bool) $existingPartner;

                if ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $selectedTurnamen)) {
                    return redirect()->route('admin.pemain.create', $request->only('id_turnamen'))
                        ->withInput()
                        ->withErrors(['no_hp' => 'Pemain 1 sudah terdaftar pada turnamen ini.']);
                }

                if ($existingPartner && $this->registrationService->isRegisteredForTournament($existingPartner, $selectedTurnamen)) {
                    return redirect()->route('admin.pemain.create', $request->only('id_turnamen'))
                        ->withInput()
                        ->withErrors(['partner_no_hp' => 'Pemain 2 sudah terdaftar pada turnamen ini.']);
                }
            } elseif ($existingPemain && $this->registrationService->isRegisteredForTournament($existingPemain, $selectedTurnamen)) {
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
            'partnerNoHp',
            'existingPemain',
            'existingPartner',
            'isPartnerExisting'
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
                ->withErrors(['no_hp' => $turnamen->isDouble()
                    ? 'Pemain 1 sudah terdaftar pada turnamen ini.'
                    : 'Pemain sudah terdaftar pada turnamen ini.']);
        }

        if ($turnamen->isDouble()) {
            $partnerNoHp = trim($request->partner_no_hp);
            $existingPartner = Pemain::where('no_hp', $partnerNoHp)->first();

            if ($existingPartner && $this->registrationService->isRegisteredForTournament($existingPartner, $turnamen)) {
                return back()
                    ->withInput()
                    ->withErrors(['partner_no_hp' => 'Pemain 2 sudah terdaftar pada turnamen ini.']);
            }

            $params['partner_no_hp'] = $partnerNoHp;
        }

        return redirect()->route('admin.pemain.create', $params);
    }

    public function store(StorePemainRequest $request)
    {
        $data = $request->validated();
        $turnamen = Turnamen::findOrFail($data['id_turnamen']);
        $this->tournamentAccess->assertTurnamenId((int) $turnamen->id);
        $status = $data['status'];

        try {
            if ($turnamen->isDouble()) {
                $pemain1 = $this->registrationService->upsertPemain([
                    'no_hp' => $data['no_hp'],
                    'nama' => $data['nama'],
                    'tgl_lahir' => $data['tgl_lahir'],
                    'gender' => $data['gender'],
                    'rating' => $data['rating'] ?? null,
                ], $request->file('foto'));

                $pemain2 = $this->registrationService->upsertPemain([
                    'no_hp' => $data['partner_no_hp'],
                    'nama' => $data['partner_nama'],
                    'tgl_lahir' => $data['partner_tgl_lahir'],
                    'gender' => $data['partner_gender'],
                    'rating' => $data['partner_rating'] ?? null,
                ], $request->file('partner_foto'));

                if ($this->registrationService->isRegisteredForTournament($pemain1, $turnamen)
                    || $this->registrationService->isRegisteredForTournament($pemain2, $turnamen)) {
                    throw new \RuntimeException('Salah satu pemain sudah terdaftar pada turnamen ini.');
                }

                TurnamenPeserta::create([
                    'id_turnamen' => $turnamen->id,
                    'id_pemain1' => $pemain1->id,
                    'id_pemain2' => $pemain2->id,
                    'status' => $status,
                ]);
            } else {
                $pemain = $this->registrationService->upsertPemain([
                    'no_hp' => $data['no_hp'],
                    'nama' => $data['nama'],
                    'tgl_lahir' => $data['tgl_lahir'],
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
                ]);
            }
        } catch (\RuntimeException $e) {
            $field = str_contains($e->getMessage(), 'pemain 2') ? 'partner_no_hp' : 'no_hp';

            return back()->withInput()->withErrors([$field => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.pemain.index', ['id_turnamen' => $turnamen->id])
            ->with('success', $turnamen->isDouble() ? 'Pasangan pemain berhasil ditambahkan.' : 'Pemain berhasil ditambahkan.');
    }

    public function createPartner(Request $request, TurnamenPeserta $peserta)
    {
        $peserta->load(['turnamen', 'pemain1']);
        $this->tournamentAccess->assertTurnamenId((int) $peserta->id_turnamen);

        if (! $peserta->turnamen->isDouble() || $peserta->id_pemain2) {
            return redirect()
                ->route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen])
                ->with('error', 'Pasangan tidak memerlukan pemain 2.');
        }

        return redirect()->route('admin.pemain.edit', [
            'pemain' => $peserta->id_pemain1,
            'id_turnamen' => $peserta->id_turnamen,
        ]);
    }

    public function partnerLookup(LookupPartnerPemainRequest $request, TurnamenPeserta $peserta)
    {
        $peserta->load(['turnamen', 'pemain1']);
        $this->tournamentAccess->assertTurnamenId((int) $peserta->id_turnamen);

        if (! $peserta->turnamen->isDouble() || $peserta->id_pemain2) {
            return redirect()
                ->route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen])
                ->with('error', 'Pasangan tidak memerlukan pemain 2.');
        }

        $noHp = trim($request->no_hp);

        if ($peserta->pemain1 && $peserta->pemain1->no_hp === $noHp) {
            return back()->withInput()->withErrors(['no_hp' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.']);
        }

        $existingPartner = Pemain::where('no_hp', $noHp)->first();

        if ($existingPartner && $this->registrationService->isRegisteredForTournament($existingPartner, $peserta->turnamen)) {
            return back()
                ->withInput()
                ->withErrors(['no_hp' => 'Pemain 2 sudah terdaftar pada turnamen ini.']);
        }

        return redirect()->route('admin.pemain.edit', [
            'pemain' => $peserta->id_pemain1,
            'id_turnamen' => $peserta->id_turnamen,
            'partner_no_hp' => $noHp,
        ]);
    }

    public function storePartner(StorePartnerPemainRequest $request, TurnamenPeserta $peserta)
    {
        $peserta->load(['turnamen', 'pemain1']);
        $this->tournamentAccess->assertTurnamenId((int) $peserta->id_turnamen);

        if (! $peserta->turnamen->isDouble() || $peserta->id_pemain2) {
            return redirect()
                ->route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen])
                ->with('error', 'Pasangan tidak memerlukan pemain 2.');
        }

        $data = $request->validated();

        if ($peserta->pemain1 && $peserta->pemain1->no_hp === trim($data['no_hp'])) {
            return back()->withInput()->withErrors(['no_hp' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.']);
        }

        try {
            $pemain2 = $this->registrationService->upsertPemain([
                'no_hp' => $data['no_hp'],
                'nama' => $data['nama'],
                'tgl_lahir' => $data['tgl_lahir'],
                'gender' => $data['gender'],
                'rating' => $data['rating'] ?? null,
            ], $request->file('foto'));

            if ($this->registrationService->isRegisteredForTournament($pemain2, $peserta->turnamen)) {
                throw new \RuntimeException('Pemain 2 sudah terdaftar pada turnamen ini.');
            }

            $peserta->update(['id_pemain2' => $pemain2->id]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['no_hp' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.pemain.edit', [
                'pemain' => $peserta->id_pemain1,
                'id_turnamen' => $peserta->id_turnamen,
            ])
            ->with('success', 'Pemain 2 berhasil ditambahkan ke pasangan.');
    }

    public function edit(Pemain $pemain)
    {
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $turnamenList = $this->matchmakingService->listForFilter();
        $turnamenPesertaEntries = TurnamenPeserta::involvingPemain($pemain->id)
            ->with('turnamen', 'pemain1', 'pemain2')
            ->get();

        $partnerPesertaQuery = TurnamenPeserta::query()
            ->where('id_pemain1', $pemain->id)
            ->whereNull('id_pemain2')
            ->whereHas('turnamen', function ($query) {
                $query->where('jenis', 'double');
            })
            ->with(['turnamen', 'pemain1']);

        if (request()->filled('id_turnamen')) {
            $partnerPesertaQuery->where('id_turnamen', (int) request('id_turnamen'));
        }

        $partnerPeserta = $partnerPesertaQuery->first();
        $partnerNoHp = trim((string) request('partner_no_hp', old('partner_no_hp', '')));
        $showPartnerForm = $partnerPeserta && $partnerNoHp !== '';
        $existingPartner = $showPartnerForm ? Pemain::where('no_hp', $partnerNoHp)->first() : null;
        $isPartnerExisting = (bool) $existingPartner;

        return view('admin.pemain.edit', compact(
            'pemain',
            'turnamenList',
            'turnamenPesertaEntries',
            'partnerPeserta',
            'partnerNoHp',
            'showPartnerForm',
            'existingPartner',
            'isPartnerExisting'
        ));
    }

    public function update(UpdatePemainRequest $request, Pemain $pemain)
    {
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $data = $request->validated();

        if (isset($data['tgl_lahir'])) {
            $data['usia'] = Carbon::parse($data['tgl_lahir'])->age;
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
            ->route('admin.pemain.index', request()->only('id_turnamen'))
            ->with('success', 'Data pemain berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Pemain $pemain)
    {
        $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
            'id_turnamen' => ['required', 'exists:m_turnamen,id'],
        ]);

        $this->tournamentAccess->assertTurnamenId((int) $request->id_turnamen);
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $peserta = TurnamenPeserta::query()
            ->forTurnamen((int) $request->id_turnamen)
            ->involvingPemain($pemain->id)
            ->first();

        if (! $peserta) {
            return response()->json([
                'success' => false,
                'message' => 'Pemain tidak terdaftar pada turnamen ini.',
            ], 422);
        }

        $peserta->update(['status' => $request->status]);

        $messages = [
            'approved' => 'Pemain berhasil disetujui.',
            'rejected' => 'Pemain ditolak.',
            'pending' => 'Status pemain dikembalikan ke pending.',
        ];

        return response()->json([
            'success' => true,
            'message' => $messages[$request->status],
            'data' => $peserta->fresh(),
        ]);
    }

    public function destroy(Pemain $pemain)
    {
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $matchQuery = Pertandingan::where(function ($q) use ($pemain) {
            $q->where('id_pemain1', $pemain->id)
                ->orWhere('id_pemain2', $pemain->id)
                ->orWhere('id_pemenang', $pemain->id);
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

        $this->photoService->delete($pemain->foto);
        $pemain->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Profil pemain berhasil dihapus.',
            ]);
        }

        return redirect()
            ->route('admin.pemain.index', request()->only('id_turnamen'))
            ->with('success', 'Profil pemain berhasil dihapus.');
    }
}
