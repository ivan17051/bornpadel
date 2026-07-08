<?php

namespace App\Services;

use App\Models\Turnamen;
use Illuminate\Http\Request;

class MatchmakingPageService
{
    protected $matchmakingService;
    protected $mahjongService;
    protected $knockoutBracketService;
    protected $tournamentCompletionService;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        MahjongMatchmakingService $mahjongService,
        KnockoutBracketService $knockoutBracketService,
        TournamentCompletionService $tournamentCompletionService
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->mahjongService = $mahjongService;
        $this->knockoutBracketService = $knockoutBracketService;
        $this->tournamentCompletionService = $tournamentCompletionService;
    }

    public function getIndexData(Request $request, ?Turnamen $turnamen = null): array
    {
        if ($turnamen === null) {
            $turnamen = $this->matchmakingService->resolveTournament(
                $request->filled('id_turnamen') ? (int) $request->id_turnamen : null,
                false
            );
        }

        $approvedCount = $turnamen
            ? $this->matchmakingService->countApprovedPlayers($turnamen)
            : 0;

        $pairingSummary = $turnamen
            ? $this->matchmakingService->getDoublePairingSummary($turnamen)
            : null;

        $groupingUnitCount = $turnamen && $turnamen->isDouble()
            ? ($turnamen->isRegistrationOpen()
                ? (int) ($pairingSummary['pairs_preview'] ?? 0)
                : $this->matchmakingService->countApprovedPairs($turnamen))
            : $approvedCount;

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
                    'pertandingan.skor',
                    'pertandingan.pemenang',
                    'pertandingan.pesertaPemenang.pemain1',
                    'pertandingan.pesertaPemenang.pemain2',
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
                    $groupingUnitCount,
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

        $hasKnockoutBracket = $turnamen && ! $isMahjong
            ? $this->knockoutBracketService->hasKnockoutBracket($turnamen)
            : false;

        $knockoutRounds = $hasKnockoutBracket
            ? $this->knockoutBracketService->getKnockoutRoundsWithMatches($turnamen)
            : collect();

        return [
            'turnamen' => $turnamen,
            'approvedCount' => $approvedCount,
            'groupingUnitCount' => $groupingUnitCount,
            'pairingSummary' => $pairingSummary,
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
            'hasKnockoutBracket' => $hasKnockoutBracket,
            'knockoutRounds' => $knockoutRounds,
            'canCompleteTournament' => $turnamen ? $this->tournamentCompletionService->canComplete($turnamen) : false,
            'mahjongIsFinal' => $turnamen && $isMahjong ? (bool) $turnamen->mahjong_is_final : false,
            'activePlayerCount' => $isMahjong && $turnamen
                ? $this->mahjongService->getGlobalRankings($turnamen)->count()
                : $approvedCount,
        ];
    }
}
