<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePemainRequest;
use App\Http\Requests\Admin\UpdatePemainRequest;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\TurnamenPeserta;
use App\Services\GroupMatchmakingService;
use App\Services\PemainPhotoService;
use App\Services\TournamentAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PemainController extends Controller
{
    protected $matchmakingService;
    protected $photoService;
    protected $tournamentAccess;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        PemainPhotoService $photoService,
        TournamentAccessService $tournamentAccess
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->photoService = $photoService;
        $this->tournamentAccess = $tournamentAccess;
    }

    public function index(Request $request)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $turnamen = $this->matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        $query = Pemain::query()->latest();

        if ($turnamen) {
            $query->whereHas('turnamenPeserta', function ($q) use ($turnamen, $request) {
                $q->where('id_turnamen', $turnamen->id);
                if ($request->filled('status')) {
                    $q->where('status', $request->status);
                }
            })->with(['turnamenPeserta' => function ($q) use ($turnamen) {
                $q->where('id_turnamen', $turnamen->id);
            }]);
        } elseif ($request->filled('status')) {
            $query->whereHas('turnamenPeserta', function ($q) use ($request) {
                $q->where('status', $request->status);
            })->with('turnamenPeserta');
        } else {
            $query->with('turnamenPeserta');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        $pemain = $query->paginate(15)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $pemain,
            ]);
        }

        return view('admin.pemain.index', compact('pemain', 'turnamen', 'turnamenList'));
    }

    public function create()
    {
        $turnamenList = $this->matchmakingService->listForFilter();

        return view('admin.pemain.create', compact('turnamenList'));
    }

    public function store(StorePemainRequest $request)
    {
        $data = $request->validated();
        $turnamenId = $data['id_turnamen'];
        $this->tournamentAccess->assertTurnamenId((int) $turnamenId);
        $status = $data['status'];
        $foto = $request->file('foto');
        unset($data['id_turnamen'], $data['status'], $data['foto']);

        $data['usia'] = Carbon::parse($data['tgl_lahir'])->age;
        $data['rating'] = $data['rating'] ?? 0;

        try {
            if ($foto) {
                $data['foto'] = $this->photoService->storeAsWebp($foto);
            }
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['foto' => $e->getMessage()]);
        }

        $pemain = Pemain::create($data);

        TurnamenPeserta::create([
            'id_turnamen' => $turnamenId,
            'id_pemain' => $pemain->id,
            'status' => $status,
        ]);

        return redirect()
            ->route('admin.pemain.index', ['id_turnamen' => $turnamenId])
            ->with('success', 'Pemain berhasil ditambahkan.');
    }

    public function edit(Pemain $pemain)
    {
        $this->tournamentAccess->assertPemainInAssignedTurnamen($pemain);

        $turnamenList = $this->matchmakingService->listForFilter();
        $pemain->load('turnamenPeserta.turnamen');

        return view('admin.pemain.edit', compact('pemain', 'turnamenList'));
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

        $peserta = TurnamenPeserta::where('id_turnamen', $request->id_turnamen)
            ->where('id_pemain', $pemain->id)
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
