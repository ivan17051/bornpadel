<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesRequestKategori;
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
use App\Services\MatchmakingPageService;
use App\Services\MatchScoringService;
use App\Services\TournamentAccessService;
use App\Services\TournamentCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MatchmakingController extends Controller
{
    use ResolvesRequestKategori;

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

    public function index(Request $request, MatchmakingPageService $matchmakingPageService)
    {
        $turnamenList = $this->matchmakingService->listForFilter();
        $data = $matchmakingPageService->getIndexData($request);

        return view('admin.matchmaking.index', array_merge($data, [
            'turnamenList' => $turnamenList,
            'filterRoute' => route('admin.matchmaking.index'),
        ]));
    }

    public function endGroupStage(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($turnamen->isMahjong()) {
            $request->validate([
                'jumlah_lolos' => ['required', 'integer', 'min:4'],
                'tiebreak_peserta_ids' => ['nullable', 'array'],
                'tiebreak_peserta_ids.*' => ['integer', 'exists:turnamen_peserta,id'],
            ], [
                'jumlah_lolos.required' => 'Jumlah pemain lolos wajib diisi.',
                'jumlah_lolos.min' => 'Minimal 4 pemain untuk babak selanjutnya.',
            ]);

            try {
                $jumlahLolos = (int) $request->input('jumlah_lolos');
                $tiebreakPesertaIds = $request->has('tiebreak_peserta_ids')
                    ? array_map('intval', $request->input('tiebreak_peserta_ids', []))
                    : null;
                $result = $this->mahjongService->advanceRound(
                    $turnamen,
                    $jumlahLolos,
                    $kategoriId,
                    $tiebreakPesertaIds
                );
            } catch (RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            if (! empty($result['needs_tiebreak'])) {
                return response()->json([
                    'success' => false,
                    'needs_tiebreak' => true,
                    'message' => sprintf(
                        'Ada %d pemain dengan poin, menang, dan akumulasi sama. Pilih %d pemain yang lolos ke babak berikutnya.',
                        count($result['contested'] ?? []),
                        (int) ($result['slots_remaining'] ?? 0)
                    ),
                    'data' => $result,
                ]);
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
            $result = $this->knockoutBracketService->generateKnockoutBracket($turnamen, $jumlahLolos, $mode, $kategoriId);
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
            'redirect_url' => route('admin.bracket.index', array_filter([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $kategoriId,
            ])),
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
            $mode = $request->input('mode', 'random');
            $result = $this->mahjongService->reshuffleGroups($turnamen, $mode, $kategoriId);
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

    public function storeMahjongGroupPointEntries(Request $request, Grup $grup)
    {
        $request->validate([
            'scores' => ['required', 'array', 'size:4'],
            'scores.*.id' => ['required', 'integer'],
            'scores.*.poin' => ['required', 'integer'],
            'id_grup_member_pemenang' => ['nullable', 'integer'],
        ]);

        try {
            $winnerMemberId = $request->filled('id_grup_member_pemenang')
                ? (int) $request->input('id_grup_member_pemenang')
                : null;
            $updatedMembers = $this->mahjongService->addGroupPointEntries(
                $grup,
                $request->input('scores'),
                $winnerMemberId
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Poin grup berhasil disimpan.',
            'data' => [
                'members' => $updatedMembers->map(function (GrupMember $member) {
                    return $this->mahjongMemberPointsPayload($member);
                })->values(),
            ],
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
            'menang' => (int) $member->menang,
            'entries' => $member->poinEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'poin' => (int) $entry->poin,
                    'is_winner' => (bool) $entry->is_winner,
                ];
            })->values(),
        ];
    }

    public function completeTournament(Request $request)
    {
        try {
            $turnamen = $this->resolveTournament($request);
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
            $result = $this->tournamentCompletionService->complete($turnamen, $kategoriId);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
            $result = $this->matchmakingService->closeRegistration($turnamen, $kategoriId);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $mode = $request->input('mode', 'random');

        if ($turnamen->isMahjong()) {
            try {
                $result = $this->mahjongService->generateGroups($turnamen, $mode, $kategoriId);
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
                if ($turnamen->competitionGrup($kategoriId)->exists()) {
                    $result = $this->friendlyService->randomizeUnassigned($turnamen, $mode, $kategoriId);
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

                $result = $this->friendlyService->generateGroups($turnamen, $mode, $kategoriId);
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
            $result = $this->matchmakingService->generateRandomGroups($turnamen, $minPerGroup, $maxPerGroup, $mode, $kategoriId);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
            $result = $this->matchmakingService->generateGroupMatches($turnamen, $kategoriId);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
            $first = GrupMember::findOrFail($data['first_member_id']);
            $second = GrupMember::findOrFail($data['second_member_id']);
            $this->matchmakingService->swapGroupMembers($turnamen, $first, $second, $kategoriId);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
            $this->matchmakingService->resetGroupsAndMatches($turnamen, $kategoriId);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
            $hasScores = $this->knockoutBracketService->hasKnockoutScores($turnamen, $kategoriId);

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

            $result = $this->knockoutBracketService->resetKnockoutBracket($turnamen, $kategoriId);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);

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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);

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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
            $result = $this->friendlyService->createSkeletonGroups($turnamen, $kategoriId);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);
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
            [, $kategoriId] = $this->resolveKategoriFromRequest($request, $turnamen);

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
            $turnamen = $this->tournamentAccess->resolveTurnamen(
                $turnamenId ? (int) $turnamenId : null,
                $this->matchmakingService,
                true
            );

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
