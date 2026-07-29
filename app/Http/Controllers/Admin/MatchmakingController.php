<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\FriendlyMatchmakingService;
use App\Services\GroupMatchmakingService;
use App\Services\KnockoutBracketService;
use App\Services\MahjongMatchmakingService;
use App\Services\MatchScoringService;
use App\Services\TournamentAccessService;
use App\Services\TournamentCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MatchmakingController extends Controller
{
    protected $matchmakingService;
    protected $mahjongService;
    protected $friendlyService;
    protected $knockoutBracketService;
    protected $tournamentAccess;
    protected $tournamentCompletionService;
    protected $scoringService;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        MahjongMatchmakingService $mahjongService,
        FriendlyMatchmakingService $friendlyService,
        KnockoutBracketService $knockoutBracketService,
        TournamentAccessService $tournamentAccess,
        TournamentCompletionService $tournamentCompletionService,
        MatchScoringService $scoringService
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->mahjongService = $mahjongService;
        $this->friendlyService = $friendlyService;
        $this->knockoutBracketService = $knockoutBracketService;
        $this->tournamentAccess = $tournamentAccess;
        $this->tournamentCompletionService = $tournamentCompletionService;
        $this->scoringService = $scoringService;
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
        $pairingSummary = $turnamen
            ? $this->matchmakingService->getDoublePairingSummary($turnamen)
            : null;
        $groupingUnitCount = $turnamen && $turnamen->playsAsPairs()
            ? ($turnamen->isRegistrationOpen()
                ? (int) ($pairingSummary['pairs_preview'] ?? 0)
                : $this->matchmakingService->countApprovedPairs($turnamen))
            : $approvedCount;

        $grup = collect();
        $groupSplitPreview = null;
        $isMahjong = $turnamen ? $turnamen->isMahjong() : false;
        $isFriendly = $turnamen ? $turnamen->isFriendly() : false;
        $friendlyMatches = collect();
        $friendlyUnassigned = collect();
        $canCreateFriendlySkeleton = false;
        $canRandomizeFriendlyUnassigned = false;

        if ($turnamen) {
            $grupQuery = $isMahjong ? $turnamen->activeGrup() : $turnamen->grup();

            $grup = $grupQuery
                ->with(array_merge([
                    'members.turnamenPeserta.pemain1',
                    'members.pemain',
                    'pertandingan.peserta1.pemain1',
                    'pertandingan.peserta2.pemain1',
                    'pertandingan.pemain1',
                    'pertandingan.pemain2',
                    'pertandingan.skor',
                    'pertandingan.pemenang',
                    'pertandingan.pesertaPemenang.pemain1',
                ], TurnamenPeserta::partnerPemainEagerLoadsFor('members.turnamenPeserta'), [
                    ...TurnamenPeserta::partnerPemainEagerLoadsFor('pertandingan.peserta1'),
                    ...TurnamenPeserta::partnerPemainEagerLoadsFor('pertandingan.peserta2'),
                    ...TurnamenPeserta::partnerPemainEagerLoadsFor('pertandingan.pesertaPemenang'),
                ]))
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
            } elseif ($isFriendly) {
                $groupSplitPreview = $this->friendlyService->previewGroupSplit($approvedCount);
                $friendlyMatches = $this->friendlyService->getMatches($turnamen)
                    ->map(function ($match) {
                        $match->setAttribute(
                            'can_edit_score',
                            $this->scoringService->canEditScore($match)
                        );

                        return $match;
                    });
                $friendlyUnassigned = $this->friendlyService->getUnassignedApprovedEntries($turnamen);
                $canCreateFriendlySkeleton = $this->friendlyService->canCreateSkeletonGroups($turnamen);
                $canRandomizeFriendlyUnassigned = $this->friendlyService->canRandomizeUnassigned($turnamen);
            } else {
                $groupSplitPreview = $this->matchmakingService->previewGroupSplit(
                    $groupingUnitCount,
                    $this->matchmakingService->getDefaultMinPerGroup(),
                    $this->matchmakingService->getDefaultMaxPerGroup()
                );
            }
        }

        $canEndGroupStage = false;
        if ($turnamen && ! $isFriendly) {
            $canEndGroupStage = $isMahjong
                ? $this->mahjongService->canAdvanceRound($turnamen)
                : $this->knockoutBracketService->canEndGroupStage($turnamen);
        }

        $hasKnockoutBracket = $turnamen && ! $isMahjong && ! $isFriendly
            ? $this->knockoutBracketService->hasKnockoutBracket($turnamen)
            : false;

        $knockoutRounds = $hasKnockoutBracket
            ? $this->knockoutBracketService->getKnockoutRoundsWithMatches($turnamen)
                ->map(function (array $round) {
                    $round['matches'] = $round['matches']->map(function ($match) {
                        $match->setAttribute(
                            'can_edit_score',
                            $this->scoringService->canEditKnockoutScore($match)
                        );

                        return $match;
                    });

                    return $round;
                })
            : collect();

        return view('admin.matchmaking.index', [
            'turnamen' => $turnamen,
            'turnamenList' => $turnamenList,
            'approvedCount' => $approvedCount,
            'groupingUnitCount' => $groupingUnitCount,
            'pairingSummary' => $pairingSummary,
            'isMahjong' => $isMahjong,
            'isFriendly' => $isFriendly,
            'friendlyMatches' => $friendlyMatches,
            'friendlyUnassigned' => $friendlyUnassigned,
            'canCreateFriendlySkeleton' => $canCreateFriendlySkeleton,
            'canRandomizeFriendlyUnassigned' => $canRandomizeFriendlyUnassigned,
            'canAddFriendlyMatch' => $turnamen && $isFriendly
                ? $this->friendlyService->canAddMatch($turnamen)
                : false,
            'unitLabel' => $turnamen ? $this->matchmakingService->unitLabel($turnamen) : 'pemain',
            'grup' => $grup,
            'groupSplitPreview' => $groupSplitPreview,
            'defaultMinPerGroup' => $this->matchmakingService->getDefaultMinPerGroup(),
            'defaultMaxPerGroup' => $this->matchmakingService->getDefaultMaxPerGroup(),
            'canCloseRegistration' => $turnamen ? $this->matchmakingService->canCloseRegistration($turnamen) : false,
            'canRandomGrup' => $turnamen ? $this->matchmakingService->canGenerateRandomGroups($turnamen) : false,
            'canEditGroups' => $turnamen ? $this->matchmakingService->canEditGroups($turnamen) : false,
            'canGenerateGroupMatches' => $turnamen ? $this->matchmakingService->canGenerateGroupMatches($turnamen) : false,
            'canResetGroupsAndMatches' => $turnamen ? $this->matchmakingService->canResetGroupsAndMatches($turnamen) : false,
            'canReshuffle' => $turnamen && $isMahjong ? $this->mahjongService->canReshuffle($turnamen) : false,
            'canEndGroupStage' => $canEndGroupStage,
            'hasKnockoutBracket' => $hasKnockoutBracket,
            'canResetKnockoutBracket' => $turnamen && ! $isMahjong
                ? $this->knockoutBracketService->canResetKnockoutBracket($turnamen)
                : false,
            'hasKnockoutScores' => $turnamen && ! $isMahjong
                ? $this->knockoutBracketService->hasKnockoutScores($turnamen)
                : false,
            'canEditGroupScores' => $turnamen && ! $isMahjong && ! $hasKnockoutBracket,
            'knockoutRounds' => $knockoutRounds,
            'canCompleteTournament' => $turnamen ? $this->tournamentCompletionService->canComplete($turnamen) : false,
            'hasPendingThirdPlacePlayoff' => $turnamen
                ? $this->tournamentCompletionService->hasPendingThirdPlacePlayoff($turnamen)
                : false,
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
            'qualification_mode' => ['nullable', 'in:per_group,total'],
            'jumlah_lolos' => ['required', 'integer', 'min:1'],
        ], [
            'jumlah_lolos.required' => 'Jumlah peserta lolos wajib diisi.',
            'jumlah_lolos.min' => 'Jumlah peserta lolos minimal 1.',
            'qualification_mode.in' => 'Mode kualifikasi tidak valid.',
        ]);

        $mode = $request->input('qualification_mode', KnockoutBracketService::QUALIFICATION_PER_GROUP);
        $jumlahLolos = (int) $request->input('jumlah_lolos');

        try {
            $result = $this->knockoutBracketService->generateKnockoutBracket($turnamen, $jumlahLolos, $mode);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $byeMessage = $result['bye_count'] > 0
            ? sprintf(' %d BYE diberikan ke unggulan teratas.', $result['bye_count'])
            : '';

        $qualificationNote = ! empty($result['qualification_summary'])
            ? ' ' . $result['qualification_summary']
            : '';

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Bracket knockout berhasil dibuat (%s) dengan %d pertandingan.%s%s',
                implode(' → ', $result['rounds']),
                $result['matches_created'],
                $byeMessage,
                $qualificationNote
            ),
            'redirect_url' => route('admin.bracket.index', ['id_turnamen' => $turnamen->id]),
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
            'poin' => ['required_without:poin_didapat', 'integer'],
            'poin_didapat' => ['required_without:poin', 'integer'],
        ]);

        try {
            $poin = $request->has('poin')
                ? (int) $request->input('poin')
                : (int) $request->input('poin_didapat');
            $updated = $this->mahjongService->addMemberPointEntry($member, $poin);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Poin berhasil ditambahkan.',
            'data' => $this->mahjongMemberPointsPayload($updated),
        ]);
    }

    public function storeMahjongPointEntry(Request $request, GrupMember $member)
    {
        $request->validate([
            'poin' => ['required', 'integer'],
        ]);

        try {
            $updated = $this->mahjongService->addMemberPointEntry($member, (int) $request->input('poin'));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Poin berhasil ditambahkan.',
            'data' => $this->mahjongMemberPointsPayload($updated),
        ]);
    }

    public function destroyMahjongPointEntry(GrupMember $member, \App\Models\MahjongPoinEntry $entry)
    {
        try {
            $updated = $this->mahjongService->deleteMemberPointEntry($member, $entry);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Entri poin dihapus.',
            'data' => $this->mahjongMemberPointsPayload($updated),
        ]);
    }

    protected function mahjongMemberPointsPayload(GrupMember $member): array
    {
        $member->loadMissing('poinEntries');

        return [
            'id' => $member->id,
            'poin_didapat' => (int) $member->poin_didapat,
            'poin_akumulasi' => (int) $member->poin_akumulasi,
            'total_poin' => $member->total_poin,
            'entries' => $member->poinEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'poin' => (int) $entry->poin,
                ];
            })->values(),
        ];
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
            'message' => $turnamen->isFriendly()
                ? 'Turnamen Friendly berhasil diselesaikan. Klasemen grup dikunci (tanpa perubahan total poin pemain).'
                : (! empty($result['cancelled_third_place'])
                    ? 'Turnamen berhasil diselesaikan. Perebutan juara 3 dibatalkan. Poin bonus juara telah ditambahkan.'
                    : 'Turnamen berhasil diselesaikan. Poin bonus juara telah ditambahkan.'),
            'data' => $result,
        ]);
    }

    public function closeRegistration(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $result = $this->matchmakingService->closeRegistration($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $message = 'Pendaftaran berhasil ditutup. Status turnamen: ongoing.';

        if ($result['pairing'] && ($result['pairing']['pairs_created'] ?? 0) > 0) {
            $message = sprintf(
                'Pendaftaran ditutup. %d pasangan dibuat secara acak dari pemain approved.',
                $result['pairing']['pairs_created']
            );
        } elseif ($turnamen->requiresPairRegistration()) {
            $message = 'Pendaftaran ditutup. Semua pasangan lengkap siap untuk pembagian grup.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'turnamen' => $result['turnamen'],
                'pairing' => $result['pairing'],
            ],
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

        if ($turnamen->isFriendly()) {
            try {
                if ($turnamen->grup()->exists()) {
                    $result = $this->friendlyService->randomizeUnassigned($turnamen, $mode);
                    $modeLabel = $mode === 'by_rating' ? 'berdasarkan rating' : 'secara acak';

                    return response()->json([
                        'success' => true,
                        'message' => sprintf(
                            'Berhasil mengacak %d pemain sisa ke grup (%s).',
                            $result['assigned_count'],
                            $modeLabel
                        ),
                        'data' => $result,
                    ]);
                }

                $result = $this->friendlyService->generateGroups($turnamen, $mode);
            } catch (RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            $modeLabel = $mode === 'by_rating' ? 'berdasarkan rating' : 'secara acak';

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Berhasil membuat %d grup Group Match dan %d slot pertandingan antar grup (%s). Isi pasangan 2v2 pada setiap slot.',
                    $result['group_count'],
                    $result['match_slots'],
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
                'Berhasil membuat %d grup (%s peserta, %s). Susunan grup masih dapat diubah.',
                count($result['groups']),
                $sizeLabel,
                $modeLabel
            ),
            'data' => $result,
        ]);
    }

    public function generateGroupMatches(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $result = $this->matchmakingService->generateGroupMatches($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Matchmaking berhasil dibuat: %d pertandingan dalam %d grup. Susunan grup sekarang dikunci.',
                $result['matches'],
                $result['groups']
            ),
            'data' => $result,
        ]);
    }

    public function swapGroupMembers(Request $request)
    {
        $data = $request->validate([
            'id_turnamen' => ['required', 'integer', 'exists:m_turnamen,id'],
            'first_member_id' => ['required', 'integer', 'different:second_member_id', 'exists:grup_member,id'],
            'second_member_id' => ['required', 'integer', 'exists:grup_member,id'],
        ]);

        try {
            $turnamen = $this->resolveTournament($request);
            $first = GrupMember::findOrFail($data['first_member_id']);
            $second = GrupMember::findOrFail($data['second_member_id']);
            $this->matchmakingService->swapGroupMembers($turnamen, $first, $second);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Peserta berhasil ditukar antar grup.',
        ]);
    }

    public function resetGroupsAndMatches(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $this->matchmakingService->resetGroupsAndMatches($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Grup dan matchmaking berhasil direset. Anda dapat membuat grup kembali.',
        ]);
    }

    public function resetKnockoutBracket(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $hasScores = $this->knockoutBracketService->hasKnockoutScores($turnamen);

            if ($hasScores) {
                $request->validate([
                    'password' => ['required', 'string'],
                ], [
                    'password.required' => 'Password wajib diisi untuk mereset bracket yang sudah ada skor.',
                ]);

                $user = $request->user();

                if (! $user || ! \Illuminate\Support\Facades\Hash::check((string) $request->input('password'), $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Password tidak valid.',
                    ], 422);
                }
            }

            $result = $this->knockoutBracketService->resetKnockoutBracket($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Validasi gagal.',
            ], 422);
        }

        $message = sprintf(
            'Bracket knockout berhasil direset (%d pertandingan dihapus). Anda dapat membuat bracket kembali.',
            $result['deleted']
        );

        if (! empty($result['had_scores'])) {
            $message .= sprintf(
                ' Poin kemenangan knockout dibatalkan (%d pertandingan).',
                $result['revoked_wins']
            );
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }

    public function createFriendlyMatch(Request $request)
    {
        $request->validate([
            'id_grup1' => ['required', 'integer', 'exists:grup,id'],
            'id_grup2' => ['required', 'integer', 'exists:grup,id', 'different:id_grup1'],
            'side1_pemain_ids' => ['required', 'array', 'size:2'],
            'side1_pemain_ids.*' => ['required', 'integer', 'exists:m_pemain,id'],
            'side2_pemain_ids' => ['required', 'array', 'size:2'],
            'side2_pemain_ids.*' => ['required', 'integer', 'exists:m_pemain,id'],
        ], [
            'id_grup2.different' => 'Pilih dua grup yang berbeda.',
            'side1_pemain_ids.size' => 'Sisi 1 harus berisi tepat 2 pemain.',
            'side2_pemain_ids.size' => 'Sisi 2 harus berisi tepat 2 pemain.',
        ]);

        try {
            $turnamen = $this->resolveTournament($request);
            $match = $this->friendlyService->createMatch(
                $turnamen,
                (int) $request->input('id_grup1'),
                (int) $request->input('id_grup2'),
                $request->input('side1_pemain_ids'),
                $request->input('side2_pemain_ids')
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan Friendly berhasil ditambahkan.',
            'data' => ['id' => $match->id],
        ]);
    }

    public function assignFriendlyPairs(Request $request, Pertandingan $pertandingan)
    {
        $request->validate([
            'side1_pemain_ids' => ['required', 'array', 'size:2'],
            'side1_pemain_ids.*' => ['required', 'integer', 'exists:m_pemain,id'],
            'side2_pemain_ids' => ['required', 'array', 'size:2'],
            'side2_pemain_ids.*' => ['required', 'integer', 'exists:m_pemain,id'],
        ], [
            'side1_pemain_ids.size' => 'Sisi 1 harus berisi tepat 2 pemain.',
            'side2_pemain_ids.size' => 'Sisi 2 harus berisi tepat 2 pemain.',
        ]);

        try {
            $turnamen = $this->resolveTournament($request);

            if ((int) $pertandingan->id_turnamen !== (int) $turnamen->id) {
                throw new RuntimeException('Pertandingan tidak termasuk turnamen ini.');
            }

            $match = $this->friendlyService->assignPairs(
                $pertandingan,
                $request->input('side1_pemain_ids'),
                $request->input('side2_pemain_ids')
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pasangan pertandingan Friendly berhasil disimpan.',
            'data' => ['id' => $match->id],
        ]);
    }

    public function deleteFriendlyMatch(Request $request, Pertandingan $pertandingan)
    {
        try {
            $turnamen = $this->resolveTournament($request);

            if ((int) $pertandingan->id_turnamen !== (int) $turnamen->id) {
                throw new RuntimeException('Pertandingan tidak termasuk turnamen ini.');
            }

            $this->friendlyService->deleteScheduledMatch($pertandingan);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan Friendly berhasil dihapus.',
        ]);
    }

    public function createFriendlySkeletonGroups(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $result = $this->friendlyService->createSkeletonGroups($turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Berhasil membuat %d kerangka grup Group Match. Susun pemain manual, lalu acak sisa bila perlu.',
                $result['group_count']
            ),
            'data' => $result,
        ]);
    }

    public function assignFriendlyMember(Request $request)
    {
        $request->validate([
            'id_grup' => ['required', 'integer', 'exists:grup,id'],
            'id_peserta' => ['required'],
        ]);

        $pesertaIds = $request->input('id_peserta');
        if (! is_array($pesertaIds)) {
            $pesertaIds = [(int) $pesertaIds];
        }

        $request->merge(['id_peserta' => array_values(array_map('intval', $pesertaIds))]);
        $request->validate([
            'id_peserta' => ['required', 'array', 'min:1'],
            'id_peserta.*' => ['integer', 'exists:turnamen_peserta,id'],
        ]);

        try {
            $turnamen = $this->resolveTournament($request);
            $members = $this->friendlyService->assignMembersToGroup(
                $turnamen,
                (int) $request->input('id_grup'),
                $pesertaIds
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $count = count($members);

        return response()->json([
            'success' => true,
            'message' => $count === 1
                ? 'Pemain berhasil dimasukkan ke grup.'
                : "{$count} pemain berhasil dimasukkan ke grup.",
            'data' => [
                'ids' => collect($members)->pluck('id')->all(),
                'count' => $count,
            ],
        ]);
    }

    public function unassignFriendlyMember(Request $request, GrupMember $member)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            $this->friendlyService->unassignMember($turnamen, $member);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pemain dilepas dari grup.',
        ]);
    }

    public function renameGrup(Request $request, Grup $grup)
    {
        $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        try {
            $turnamen = $this->resolveTournament($request);

            if (! $turnamen->isFriendly()) {
                throw new RuntimeException('Rename grup saat ini hanya untuk Group Match.');
            }

            $grup = $this->friendlyService->renameGroup($turnamen, $grup, $request->input('nama'));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nama grup berhasil diperbarui.',
            'data' => ['id' => $grup->id, 'nama' => $grup->nama],
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
