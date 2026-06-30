<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrupMember;
use App\Models\Turnamen;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\MahjongMatchmakingService;
use App\Services\TournamentAccessService;
use App\Services\TournamentCompletionService;
use Illuminate\Http\Request;
use RuntimeException;

class MatchmakingController extends Controller
{
    protected $matchmakingService;
    protected $mahjongService;
    protected $knockoutBracketService;
    protected $tournamentAccess;
    protected $tournamentCompletionService;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        MahjongMatchmakingService $mahjongService,
        KnockoutBracketService $knockoutBracketService,
        TournamentAccessService $tournamentAccess,
        TournamentCompletionService $tournamentCompletionService
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->mahjongService = $mahjongService;
        $this->knockoutBracketService = $knockoutBracketService;
        $this->tournamentAccess = $tournamentAccess;
        $this->tournamentCompletionService = $tournamentCompletionService;
    }

    public function index(Request $request)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $turnamen = $this->matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
            false
        );
        $approvedCount = $turnamen
            ? $this->matchmakingService->countApprovedPlayers($turnamen)
            : 0;

        $grup = collect();
        $groupSplitPreview = null;
        $isMahjong = $turnamen ? $turnamen->isMahjong() : false;

        if ($turnamen) {
            $grupQuery = $isMahjong ? $turnamen->activeGrup() : $turnamen->grup();

            $grup = $grupQuery
                ->with([
                    'members.turnamenPeserta.pemain1',
                    'members.turnamenPeserta.pemain2',
                    'members.pemain',
                    'pertandingan.peserta1.pemain1',
                    'pertandingan.peserta1.pemain2',
                    'pertandingan.peserta2.pemain1',
                    'pertandingan.peserta2.pemain2',
                    'pertandingan.pemain1',
                    'pertandingan.pemain2',
                ])
                ->orderBy('nama')
                ->get();

            if ($isMahjong) {
                $mahjongGroupCount = $approvedCount >= 4 ? intdiv($approvedCount, 4) : 0;
                $groupSplitPreview = $mahjongGroupCount > 0
                    ? [
                        'group_count' => $mahjongGroupCount,
                        'sizes' => array_fill(0, $mahjongGroupCount, 4),
                        'label' => implode(' + ', array_fill(0, $mahjongGroupCount, 4)),
                    ]
                    : null;
            } else {
                $groupSplitPreview = $this->matchmakingService->previewGroupSplit(
                    $approvedCount,
                    $this->matchmakingService->getDefaultMinPerGroup(),
                    $this->matchmakingService->getDefaultMaxPerGroup()
                );
            }
        }

        $canEndGroupStage = false;
        if ($turnamen) {
            $canEndGroupStage = $isMahjong
                ? $this->mahjongService->canAdvanceRound($turnamen)
                : $this->knockoutBracketService->canEndGroupStage($turnamen);
        }

        return view('admin.matchmaking.index', [
            'turnamen' => $turnamen,
            'turnamenList' => $turnamenList,
            'approvedCount' => $approvedCount,
            'isMahjong' => $isMahjong,
            'unitLabel' => $turnamen ? $this->matchmakingService->unitLabel($turnamen) : 'pemain',
            'grup' => $grup,
            'groupSplitPreview' => $groupSplitPreview,
            'defaultMinPerGroup' => $this->matchmakingService->getDefaultMinPerGroup(),
            'defaultMaxPerGroup' => $this->matchmakingService->getDefaultMaxPerGroup(),
            'canCloseRegistration' => $turnamen ? $this->matchmakingService->canCloseRegistration($turnamen) : false,
            'canRandomGrup' => $turnamen ? $this->matchmakingService->canGenerateRandomGroups($turnamen) : false,
            'canReshuffle' => $turnamen && $isMahjong ? $this->mahjongService->canReshuffle($turnamen) : false,
            'canEndGroupStage' => $canEndGroupStage,
            'hasKnockoutBracket' => $turnamen && ! $isMahjong ? $this->knockoutBracketService->hasKnockoutBracket($turnamen) : false,
            'canCompleteTournament' => $turnamen ? $this->tournamentCompletionService->canComplete($turnamen) : false,
            'mahjongIsFinal' => $turnamen && $isMahjong ? (bool) $turnamen->mahjong_is_final : false,
            'activePlayerCount' => $isMahjong && $turnamen ? $this->mahjongService->getGlobalRankings($turnamen)->count() : $approvedCount,
        ]);
    }

    public function endGroupStage(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($turnamen->isMahjong()) {
            $request->validate([
                'jumlah_lolos' => ['required', 'integer', 'min:4'],
            ], [
                'jumlah_lolos.required' => 'Jumlah pemain lolos wajib diisi.',
                'jumlah_lolos.min' => 'Minimal 4 pemain untuk babak selanjutnya.',
            ]);

            try {
                $jumlahLolos = (int) $request->input('jumlah_lolos');
                $result = $this->mahjongService->advanceRound($turnamen, $jumlahLolos);
            } catch (RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            $message = $result['is_final']
                ? 'Grup final berisi 4 pemain. Input poin babak final lalu selesaikan turnamen.'
                : sprintf(
                    'Babak %d dibuat: %d pemain lolos dalam %d grup.',
                    $result['babak'],
                    $result['qualifiers'],
                    count($result['groups'])
                );

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        }

        $request->validate([
            'jumlah_lolos' => ['required', 'integer', 'min:1', 'max:8'],
        ], [
            'jumlah_lolos.required' => 'Jumlah peserta lolos wajib diisi.',
            'jumlah_lolos.min' => 'Jumlah peserta lolos minimal 1.',
        ]);

        try {
            $jumlahLolos = (int) $request->input('jumlah_lolos');
            $result = $this->knockoutBracketService->generateKnockoutBracket($turnamen, $jumlahLolos);
            $result['jumlah_lolos_per_grup'] = $jumlahLolos;
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $byeMessage = $result['bye_count'] > 0
            ? sprintf(' %d BYE diberikan ke unggulan teratas.', $result['bye_count'])
            : '';

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Bracket knockout berhasil dibuat (%s) dengan %d pertandingan.%s',
                implode(' → ', $result['rounds']),
                $result['matches_created'],
                $byeMessage
            ),
            'data' => $result,
        ]);
    }

    public function reshuffleGroups(Request $request)
    {
        $request->validate([
            'mode' => ['nullable', 'in:random,by_rating'],
        ]);

        try {
            $turnamen = $this->resolveTournament($request);
            $mode = $request->input('mode', 'random');
            $result = $this->mahjongService->reshuffleGroups($turnamen, $mode);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Grup berhasil diacak ulang (%d grup, poin pemain dipertahankan).',
                count($result['groups'])
            ),
            'data' => $result,
        ]);
    }

    public function updateMahjongPoints(Request $request, GrupMember $member)
    {
        $request->validate([
            'poin_didapat' => ['required', 'integer', 'min:0'],
        ]);

        $member->load('grup.turnamen');

        if (! $member->grup || ! $member->grup->turnamen || ! $member->grup->turnamen->isMahjong()) {
            return response()->json([
                'success' => false,
                'message' => 'Pembaruan poin hanya untuk turnamen Mahjong.',
            ], 422);
        }

        if (! $member->grup->is_aktif) {
            return response()->json([
                'success' => false,
                'message' => 'Grup tidak aktif.',
            ], 422);
        }

        $updated = $this->mahjongService->updateMemberPoints($member, (int) $request->input('poin_didapat'));

        return response()->json([
            'success' => true,
            'message' => 'Poin berhasil diperbarui.',
            'data' => [
                'id' => $updated->id,
                'poin_didapat' => (int) $updated->poin_didapat,
                'poin_akumulasi' => (int) $updated->poin_akumulasi,
                'total_poin' => $updated->total_poin,
            ],
        ]);
    }

    public function completeTournament(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $result = $this->tournamentCompletionService->complete($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Turnamen berhasil diselesaikan. Poin bonus juara telah ditambahkan.',
            'data' => $result,
        ]);
    }

    public function closeRegistration(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $turnamen = $this->matchmakingService->closeRegistration($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil ditutup. Status turnamen: ongoing.',
            'data' => $turnamen,
        ]);
    }

    public function randomGrup(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $mode = $request->input('mode', 'random');

        if ($turnamen->isMahjong()) {
            try {
                $result = $this->mahjongService->generateGroups($turnamen, $mode);
            } catch (RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            $modeLabel = $mode === 'by_rating' ? 'berdasarkan rating' : 'secara acak';

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Berhasil membuat %d grup Mahjong (4 pemain per grup, %s).',
                    count($result['groups']),
                    $modeLabel
                ),
                'data' => $result,
            ]);
        }

        $request->validate([
            'mode' => ['nullable', 'in:random,by_rating'],
            'min_pemain_grup' => ['required', 'integer', 'min:2', 'max:12'],
            'max_pemain_grup' => ['required', 'integer', 'min:2', 'max:12', 'gte:min_pemain_grup'],
        ], [
            'min_pemain_grup.required' => 'Minimum pemain per grup wajib diisi.',
            'max_pemain_grup.required' => 'Maksimum pemain per grup wajib diisi.',
            'max_pemain_grup.gte' => 'Maksimum pemain per grup tidak boleh lebih kecil dari minimum.',
        ]);

        $minPerGroup = (int) $request->input('min_pemain_grup');
        $maxPerGroup = (int) $request->input('max_pemain_grup');

        try {
            $result = $this->matchmakingService->generateRandomGroups(
                $turnamen,
                $minPerGroup,
                $maxPerGroup,
                $mode
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $modeLabel = $mode === 'by_rating'
            ? 'berdasarkan rating (pemain dengan rating serupa dalam satu grup)'
            : 'secara acak';

        $sizeLabel = implode(' + ', $result['group_sizes']);

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Berhasil membuat %d grup (%s pemain) dan %d pertandingan fase grup (%s).',
                count($result['groups']),
                $sizeLabel,
                $result['matches'],
                $modeLabel
            ),
            'data' => $result,
        ]);
    }

    protected function resolveTournament(Request $request): Turnamen
    {
        $request->validate([
            'id_turnamen' => ['nullable', 'exists:m_turnamen,id'],
            'tournament_id' => ['nullable', 'exists:m_turnamen,id'],
        ]);

        $turnamenId = $request->input('tournament_id') ?? $request->input('id_turnamen');

        if ($this->tournamentAccess->isPanitia()) {
            $turnamen = $this->tournamentAccess->assignedTurnamen();

            if (! $turnamen) {
                throw new RuntimeException('Akun panitia belum ditugaskan ke turnamen.');
            }

            return $turnamen;
        }

        if ($request->filled('id_turnamen') || $request->filled('tournament_id')) {
            return Turnamen::findOrFail($turnamenId);
        }

        $turnamen = $this->matchmakingService->getActiveTournament();

        if (! $turnamen) {
            throw new RuntimeException('Tidak ada turnamen aktif.');
        }

        return $turnamen;
    }
}
