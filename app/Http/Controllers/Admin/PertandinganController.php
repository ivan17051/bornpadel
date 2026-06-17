<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMatchScoreRequest;
use App\Models\Grup;
use App\Models\Pertandingan;
use App\Services\GroupMatchmakingService;
use App\Services\MatchScoringService;
use App\Services\TournamentAccessService;
use Illuminate\Http\Request;
use RuntimeException;

class PertandinganController extends Controller
{
    protected $scoringService;
    protected $matchmakingService;
    protected $tournamentAccess;

    public function __construct(
        MatchScoringService $scoringService,
        GroupMatchmakingService $matchmakingService,
        TournamentAccessService $tournamentAccess
    ) {
        $this->scoringService = $scoringService;
        $this->matchmakingService = $matchmakingService;
        $this->tournamentAccess = $tournamentAccess;
    }

    public function index(Request $request)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $turnamen = $this->matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        $query = Pertandingan::with(['pemain1', 'pemain2', 'pemenang', 'grup', 'skor'])
            ->orderBy('nama_ronde')
            ->orderBy('id_grup')
            ->orderBy('id');

        if ($turnamen) {
            $query->where('id_turnamen', $turnamen->id);
        }

        if ($request->filled('nama_ronde')) {
            $query->where('nama_ronde', $request->nama_ronde);
        }

        if ($request->filled('id_grup')) {
            $query->where('id_grup', $request->id_grup);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $pertandingan = $query->paginate(20)->withQueryString();

        $grupList = $turnamen
            ? Grup::where('id_turnamen', $turnamen->id)->orderBy('nama')->get()
            : collect();

        $rondeOptions = ['Fase Grup', 'Perempatfinal', 'Semifinal', 'Final'];

        return view('admin.pertandingan.index', compact(
            'pertandingan',
            'grupList',
            'rondeOptions',
            'turnamen',
            'turnamenList'
        ));
    }

    public function show(Pertandingan $pertandingan)
    {
        $this->tournamentAccess->assertPertandinganAccess($pertandingan);

        $pertandingan->load(['pemain1', 'pemain2', 'pemenang', 'grup', 'skor']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $pertandingan->id,
                'nama_ronde' => $pertandingan->nama_ronde,
                'grup' => $pertandingan->grup ? $pertandingan->grup->nama : null,
                'status' => $pertandingan->status,
                'pemain1' => [
                    'id' => $pertandingan->id_pemain1,
                    'nama' => $pertandingan->pemain1 ? $pertandingan->pemain1->nama : 'TBD',
                ],
                'pemain2' => [
                    'id' => $pertandingan->id_pemain2,
                    'nama' => $pertandingan->pemain2 ? $pertandingan->pemain2->nama : 'TBD',
                ],
                'ready_for_scoring' => $pertandingan->isReadyForScoring(),
                'pemenang_id' => $pertandingan->id_pemenang,
                'skor' => $pertandingan->skor->map(function ($s) {
                    return [
                        'set_ke' => $s->set_ke,
                        'skor_pemain1' => $s->skor_pemain1,
                        'skor_pemain2' => $s->skor_pemain2,
                    ];
                }),
            ],
        ]);
    }

    public function storeScore(StoreMatchScoreRequest $request, Pertandingan $pertandingan)
    {
        $this->tournamentAccess->assertPertandinganAccess($pertandingan);

        try {
            $pertandingan = $this->scoringService->recordScore($pertandingan, $request->validated()['sets']);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Skor berhasil disimpan. Klasemen grup telah diperbarui.',
            'data' => [
                'id' => $pertandingan->id,
                'status' => $pertandingan->status,
                'pemenang' => $pertandingan->pemenang->nama ?? null,
                'skor' => $pertandingan->skor,
            ],
        ]);
    }
}
