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
        $selectedTurnamen = $request->filled('id_turnamen')
            ? Turnamen::find($request->id_turnamen)
            : null;
        $showForm = $noHp !== '' && $selectedTurnamen;

        $existingPemain = null;
        $existingPartner = null;

        if ($showForm) {
            $existingPemain = Pemain::where('no_hp', $noHp)->first();
            $partnerNoHp = trim((string) old('partner_no_hp', ''));
            if ($partnerNoHp !== '') {
                $existingPartner = Pemain::where('no_hp', $partnerNoHp)->first();
            }
        }

        return view('admin.pemain.create', compact(
            'turnamenList',
            'selectedTurnamen',
            'showForm',
            'noHp',
            'existingPemain',
            'existingPartner'
        ));
    }

    public function lookup(LookupPemainRequest $request)
    {
        $this->tournamentAccess->assertTurnamenId((int) $request->id_turnamen);

        return redirect()->route('admin.pemain.create', [
            'id_turnamen' => $request->id_turnamen,
            'no_hp' => trim($request->no_hp),
            'status' => $request->input('status', 'approved'),
        ]);
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
            return back()->withInput()->withErrors(['no_hp' => $e->getMessage()]);
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

        $noHp = trim((string) $request->get('no_hp', old('no_hp', '')));
        $showForm = $noHp !== '';
        $existingPemain = $showForm ? Pemain::where('no_hp', $noHp)->first() : null;

        return view('admin.pemain.add-partner', compact('peserta', 'showForm', 'noHp', 'existingPemain'));
    }

    public function partnerLookup(LookupPartnerPemainRequest $request, TurnamenPeserta $peserta)
    {
        $peserta->load(['turnamen', 'pemain1']);
        $this->tournamentAccess->assertTurnamenId((int) $peserta->id_turnamen);

        $noHp = trim($request->no_hp);

        if ($peserta->pemain1 && $peserta->pemain1->no_hp === $noHp) {
            return back()->withInput()->withErrors(['no_hp' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.']);
        }

        return redirect()->route('admin.pemain.peserta.partner.create', [
            'peserta' => $peserta->id,
            'no_hp' => $noHp,
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
            ->route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen])
            ->with('success', 'Pemain 2 berhasil ditambahkan ke pasangan.');
    }

    public function edit(Pemain $pemain)
    {
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $turnamenList = $this->matchmakingService->listForFilter();
        $turnamenPesertaEntries = TurnamenPeserta::involvingPemain($pemain->id)
            ->with('turnamen', 'pemain2')
            ->get();

        return view('admin.pemain.edit', compact('pemain', 'turnamenList', 'turnamenPesertaEntries'));
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
