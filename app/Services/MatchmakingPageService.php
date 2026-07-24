<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Http\Request;

class MatchmakingPageService
{
    protected $matchmakingService;
    protected $mahjongService;
    protected $friendlyService;
    protected $knockoutBracketService;
    protected $tournamentCompletionService;
    protected $scoringService;

    public function __construct(
        GroupMatchmakingService $matchmakingService,
        MahjongMatchmakingService $mahjongService,
        FriendlyMatchmakingService $friendlyService,
        KnockoutBracketService $knockoutBracketService,
        TournamentCompletionService $tournamentCompletionService,
        MatchScoringService $scoringService
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->mahjongService = $mahjongService;
        $this->friendlyService = $friendlyService;
        $this->knockoutBracketService = $knockoutBracketService;
        $this->tournamentCompletionService = $tournamentCompletionService;
        $this->scoringService = $scoringService;
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
        $isFriendly = $turnamen ? $turnamen->isFriendly() : false;
        $friendlyMatches = collect();

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

        return [
            'turnamen' => $turnamen,
            'approvedCount' => $approvedCount,
            'groupingUnitCount' => $groupingUnitCount,
            'pairingSummary' => $pairingSummary,
            'isMahjong' => $isMahjong,
            'isFriendly' => $isFriendly,
            'friendlyMatches' => $friendlyMatches,
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
            'activePlayerCount' => $isMahjong && $turnamen
                ? $this->mahjongService->getGlobalRankings($turnamen)->count()
                : $approvedCount,
        ];
    }
}
