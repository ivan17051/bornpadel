<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Turnamen;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\TournamentAccessService;
use Illuminate\Http\Request;
use RuntimeException;

class MatchmakingController extends Controller
{
    protected $matchmakingService;
    protected $knockoutBracketService;
    protected $tournamentAccess;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        KnockoutBracketService $knockoutBracketService,
        TournamentAccessService $tournamentAccess
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->knockoutBracketService = $knockoutBracketService;
        $this->tournamentAccess = $tournamentAccess;
    }

    public function index(Request $request)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $turnamen = $this->matchmakingService->resolveTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );
        $approvedCount = $turnamen
            ? $this->matchmakingService->countApprovedPlayers($turnamen)
            : 0;

        $grup = collect();
        $groupSplitPreview = null;
        if ($turnamen) {
            $grup = $turnamen->grup()
                ->with(['pemain', 'pertandingan.pemain1', 'pertandingan.pemain2'])
                ->get();

            $groupSplitPreview = $this->matchmakingService->previewGroupSplit(
                $approvedCount,
                $this->matchmakingService->getDefaultMinPerGroup(),
                $this->matchmakingService->getDefaultMaxPerGroup()
            );
        }

        return view('admin.matchmaking.index', [
            'turnamen' => $turnamen,
            'turnamenList' => $turnamenList,
            'approvedCount' => $approvedCount,
            'grup' => $grup,
            'groupSplitPreview' => $groupSplitPreview,
            'defaultMinPerGroup' => $this->matchmakingService->getDefaultMinPerGroup(),
            'defaultMaxPerGroup' => $this->matchmakingService->getDefaultMaxPerGroup(),
            'canCloseRegistration' => $turnamen ? $this->matchmakingService->canCloseRegistration($turnamen) : false,
            'canRandomGrup' => $turnamen ? $this->matchmakingService->canGenerateRandomGroups($turnamen) : false,
            'canEndGroupStage' => $turnamen ? $this->knockoutBracketService->canEndGroupStage($turnamen) : false,
            'hasKnockoutBracket' => $turnamen ? $this->knockoutBracketService->hasKnockoutBracket($turnamen) : false,
        ]);
    }

    public function endGroupStage(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $result = $this->knockoutBracketService->generateKnockoutBracket($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Bracket knockout berhasil dibuat (%s) dengan %d pertandingan.',
                implode(' → ', $result['rounds']),
                $result['matches_created']
            ),
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
        $request->validate([
            'mode' => ['nullable', 'in:random,by_rating'],
            'min_pemain_grup' => ['required', 'integer', 'min:2', 'max:12'],
            'max_pemain_grup' => ['required', 'integer', 'min:2', 'max:12', 'gte:min_pemain_grup'],
        ], [
            'min_pemain_grup.required' => 'Minimum pemain per grup wajib diisi.',
            'max_pemain_grup.required' => 'Maksimum pemain per grup wajib diisi.',
            'max_pemain_grup.gte' => 'Maksimum pemain per grup tidak boleh lebih kecil dari minimum.',
        ]);

        $mode = $request->input('mode', 'random');
        $minPerGroup = (int) $request->input('min_pemain_grup');
        $maxPerGroup = (int) $request->input('max_pemain_grup');

        try {
            $turnamen = $this->resolveTournament($request);
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
        ]);

        if ($this->tournamentAccess->isPanitia()) {
            $turnamen = $this->tournamentAccess->assignedTurnamen();

            if (! $turnamen) {
                throw new RuntimeException('Akun panitia belum ditugaskan ke turnamen.');
            }

            return $turnamen;
        }

        if ($request->filled('id_turnamen')) {
            return Turnamen::findOrFail($request->id_turnamen);
        }

        $turnamen = $this->matchmakingService->getActiveTournament();

        if (! $turnamen) {
            throw new RuntimeException('Tidak ada turnamen aktif.');
        }

        return $turnamen;
    }
}
