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



        $kategori = null;

        $kategoriId = null;

        $kategoriList = collect();



        if ($turnamen) {

            $kategoriList = $turnamen->kategori()->ordered()->get();

            $requestedKategoriId = $request->filled('id_kategori') ? (int) $request->id_kategori : null;



            try {

                $kategori = $turnamen->resolveKategori($requestedKategoriId);

            } catch (\RuntimeException $e) {

                $kategori = $turnamen->resolveKategori();

            }



            $kategoriId = (int) $kategori->id;

        }



        $approvedCount = $turnamen

            ? $this->matchmakingService->countApprovedPlayers($turnamen, $kategoriId)

            : 0;



        $pairingSummary = $turnamen

            ? $this->matchmakingService->getDoublePairingSummary($turnamen, $kategoriId)

            : null;



        $groupingUnitCount = $turnamen && $turnamen->playsAsPairs()

            ? ($kategori && $kategori->isRegistrationOpen()

                ? (int) ($pairingSummary['pairs_preview'] ?? 0)

                : $this->matchmakingService->countApprovedPairs($turnamen, $kategoriId))

            : $approvedCount;



        $grup = collect();

        $groupSplitPreview = null;

        $isMahjong = $turnamen ? $turnamen->isMahjong() : false;

        $isFriendly = $turnamen ? $turnamen->isFriendly() : false;

        $friendlyMatches = collect();

        $friendlyUnassigned = collect();

        $friendlyRegistrationGroups = collect();

        $canCreateFriendlySkeleton = false;

        $canRandomizeFriendlyUnassigned = false;

        $friendlyPlayersPerGroup = $kategori && $isFriendly

            ? $kategori->friendlyPlayersPerGroup()

            : ($turnamen && $isFriendly

                ? $turnamen->friendlyPlayersPerGroup()

                : \App\Models\Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP);



        if ($turnamen && $kategori) {

            $grupQuery = $isMahjong ? $kategori->activeGrup() : $kategori->grup();



            $grup = $grupQuery

                ->with(array_merge([

                    'members.turnamenPeserta.pemain1',

                    'members.pemain',

                    'members.poinEntries',

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

                $friendlyPlayersPerGroup = $kategori->friendlyPlayersPerGroup();

                $groupSplitPreview = $this->friendlyService->previewGroupSplit(

                    $approvedCount,

                    $friendlyPlayersPerGroup

                );

                $friendlyMatches = $this->friendlyService->getMatches($turnamen, $kategoriId)

                    ->map(function ($match) {

                        $match->setAttribute(

                            'can_edit_score',

                            $this->scoringService->canEditScore($match)

                        );



                        return $match;

                    });

                $friendlyUnassigned = $this->friendlyService->getUnassignedApprovedEntries($turnamen, $kategoriId);

                $canCreateFriendlySkeleton = $this->friendlyService->canCreateSkeletonGroups($turnamen, $kategoriId);

                $canRandomizeFriendlyUnassigned = $this->friendlyService->canRandomizeUnassigned($turnamen, $kategoriId);



                if ($kategori->isRegistrationOpen() || $grup->isEmpty()) {

                    $friendlyRegistrationGroups = $this->friendlyService->getFriendlyRegistrationGroups($turnamen, $kategoriId);

                }

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

                ? $this->mahjongService->canAdvanceRound($turnamen, $kategoriId)

                : $this->knockoutBracketService->canEndGroupStage($turnamen, $kategoriId);

        }



        $hasKnockoutBracket = $turnamen && ! $isMahjong && ! $isFriendly

            ? $this->knockoutBracketService->hasKnockoutBracket($turnamen, $kategoriId)

            : false;



        $knockoutRounds = $hasKnockoutBracket

            ? $this->knockoutBracketService->getKnockoutRoundsWithMatches($turnamen, $kategoriId)

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



        $registrationOpen = $kategori

            ? $kategori->isRegistrationOpen()

            : ($turnamen ? $turnamen->isRegistrationOpen() : false);



        return [

            'turnamen' => $turnamen,

            'kategori' => $kategori,

            'kategoriList' => $kategoriList,

            'kategoriId' => $kategoriId,

            'hasMultipleKategori' => $turnamen ? $turnamen->hasMultipleKategori() : false,

            'registrationOpen' => $registrationOpen,

            'approvedCount' => $approvedCount,

            'groupingUnitCount' => $groupingUnitCount,

            'pairingSummary' => $pairingSummary,

            'isMahjong' => $isMahjong,

            'isFriendly' => $isFriendly,

            'friendlyPlayersPerGroup' => $friendlyPlayersPerGroup,

            'friendlyMatches' => $friendlyMatches,

            'friendlyUnassigned' => $friendlyUnassigned,

            'friendlyRegistrationGroups' => $friendlyRegistrationGroups,

            'canCreateFriendlySkeleton' => $canCreateFriendlySkeleton,

            'canRandomizeFriendlyUnassigned' => $canRandomizeFriendlyUnassigned,

            'canAddFriendlyMatch' => $turnamen && $isFriendly

                ? $this->friendlyService->canAddMatch($turnamen, $kategoriId)

                : false,

            'unitLabel' => $turnamen ? $this->matchmakingService->unitLabel($turnamen) : 'pemain',

            'grup' => $grup,

            'groupSplitPreview' => $groupSplitPreview,

            'defaultMinPerGroup' => $this->matchmakingService->getDefaultMinPerGroup(),

            'defaultMaxPerGroup' => $this->matchmakingService->getDefaultMaxPerGroup(),

            'canCloseRegistration' => $turnamen

                ? $this->matchmakingService->canCloseRegistration($turnamen, $kategoriId)

                : false,

            'canRandomGrup' => $turnamen

                ? $this->matchmakingService->canGenerateRandomGroups($turnamen, $kategoriId)

                : false,

            'canEditGroups' => $turnamen

                ? $this->matchmakingService->canEditGroups($turnamen, $kategoriId)

                : false,

            'canGenerateGroupMatches' => $turnamen

                ? $this->matchmakingService->canGenerateGroupMatches($turnamen, $kategoriId)

                : false,

            'canResetGroupsAndMatches' => $turnamen

                ? $this->matchmakingService->canResetGroupsAndMatches($turnamen, $kategoriId)

                : false,

            'canReshuffle' => $turnamen && $isMahjong

                ? $this->mahjongService->canReshuffle($turnamen, $kategoriId)

                : false,

            'canEndGroupStage' => $canEndGroupStage,

            'hasKnockoutBracket' => $hasKnockoutBracket,

            'canResetKnockoutBracket' => $turnamen && ! $isMahjong

                ? $this->knockoutBracketService->canResetKnockoutBracket($turnamen, $kategoriId)

                : false,

            'hasKnockoutScores' => $turnamen && ! $isMahjong

                ? $this->knockoutBracketService->hasKnockoutScores($turnamen, $kategoriId)

                : false,

            'canEditGroupScores' => $turnamen && ! $isMahjong && ! $hasKnockoutBracket,

            'knockoutRounds' => $knockoutRounds,

            'canCompleteTournament' => $turnamen

                ? $this->tournamentCompletionService->canComplete($turnamen, $kategoriId)

                : false,

            'hasPendingThirdPlacePlayoff' => $turnamen

                ? $this->tournamentCompletionService->hasPendingThirdPlacePlayoff($turnamen, $kategoriId)

                : false,

            'mahjongIsFinal' => $turnamen && $isMahjong

                ? (bool) $turnamen->categoryMahjongIsFinal($kategoriId)

                : false,

            'activePlayerCount' => $isMahjong && $turnamen

                ? $this->mahjongService->getGlobalRankings($turnamen, $kategoriId)->count()

                : $approvedCount,

        ];

    }

}

