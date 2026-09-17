<?php



namespace App\Services;



use App\Models\Turnamen;

use App\Models\TurnamenPeserta;

use Illuminate\Http\Request;



class MatchmakingPageService

{

    protected $matchmakingService;

    protected $mahjongService;

    protected $mahjongTeamService;

    protected $friendlyService;

    protected $knockoutBracketService;

    protected $tournamentCompletionService;

    protected $scoringService;



    public function __construct(

        GroupMatchmakingService $matchmakingService,

        MahjongMatchmakingService $mahjongService,

        MahjongTeamMatchmakingService $mahjongTeamService,

        FriendlyMatchmakingService $friendlyService,

        KnockoutBracketService $knockoutBracketService,

        TournamentCompletionService $tournamentCompletionService,

        MatchScoringService $scoringService

    ) {

        $this->matchmakingService = $matchmakingService;

        $this->mahjongService = $mahjongService;

        $this->mahjongTeamService = $mahjongTeamService;

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

        $isMahjongTeam = $turnamen ? $turnamen->isMahjongTeam() : false;

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

            $grupQuery = ($isMahjong || $isMahjongTeam) ? $kategori->activeGrup() : $kategori->grup();



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



            if ($isMahjong || $isMahjongTeam) {

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

            if ($isMahjong) {

                $canEndGroupStage = $this->mahjongService->canAdvanceRound($turnamen, $kategoriId);

            } elseif ($isMahjongTeam) {

                $canEndGroupStage = $this->mahjongTeamService->canAdvanceBabak($turnamen, $kategoriId);

            } else {

                $canEndGroupStage = $this->knockoutBracketService->canEndGroupStage($turnamen, $kategoriId);

            }

        }



        $hasKnockoutBracket = $turnamen && ! $isMahjong && ! $isMahjongTeam && ! $isFriendly

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

            'isMahjongTeam' => $isMahjongTeam,

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

                : ($turnamen && $isMahjongTeam

                    ? $this->mahjongTeamService->canReshuffleMeja($turnamen, $kategoriId)

                    : false),

            'canEndGroupStage' => $canEndGroupStage,

            'hasKnockoutBracket' => $hasKnockoutBracket,

            'canResetKnockoutBracket' => $turnamen && ! $isMahjong && ! $isMahjongTeam

                ? $this->knockoutBracketService->canResetKnockoutBracket($turnamen, $kategoriId)

                : false,

            'hasKnockoutScores' => $turnamen && ! $isMahjong && ! $isMahjongTeam

                ? $this->knockoutBracketService->hasKnockoutScores($turnamen, $kategoriId)

                : false,

            'canEditGroupScores' => $turnamen && ! $isMahjong && ! $isMahjongTeam && ! $hasKnockoutBracket,

            'knockoutRounds' => $knockoutRounds,

            'canCompleteTournament' => $turnamen

                ? $this->tournamentCompletionService->canComplete($turnamen, $kategoriId)

                : false,

            'hasPendingThirdPlacePlayoff' => $turnamen

                ? $this->tournamentCompletionService->hasPendingThirdPlacePlayoff($turnamen, $kategoriId)

                : false,

            'mahjongIsFinal' => $turnamen && ($isMahjong || $isMahjongTeam)

                ? (bool) $turnamen->categoryMahjongIsFinal($kategoriId)

                : false,

            'mahjongExternalScoringEnabled' => $turnamen && $isMahjong && $kategoriId

                ? $this->mahjongService->isExternalScoringEnabled($turnamen, $kategoriId)

                : false,

            'mahjongPriorBabakBreakdown' => $turnamen && $isMahjong && $kategoriId

                ? $this->mahjongService->getPriorBabakBreakdown($turnamen, $kategoriId)

                : [],

            'mahjongHistory' => $turnamen && $isMahjong && $kategoriId

                ? $this->mahjongService->getMatchmakingHistory($turnamen, $kategoriId)

                : collect(),

            'mahjongTeamStandings' => $turnamen && $isMahjongTeam && $kategoriId

                ? $this->mahjongTeamService->teamStandings($turnamen, $kategoriId)

                : collect(),

            'mahjongTeamMeja' => $turnamen && $isMahjongTeam && $kategoriId

                ? \App\Models\TurnamenMeja::query()
                    ->where('id_kategori', $kategoriId)
                    ->where('is_aktif', true)
                    ->with([
                        'seats.grupMember.pemain',
                        'seats.grupMember.turnamenPeserta.pemain1',
                        'seats.grupMember.grup',
                        'seats.grupMember.poinEntries',
                    ])
                    ->orderBy('nama')
                    ->orderBy('id')
                    ->get()

                : collect(),

            'mahjongTeamHistory' => $turnamen && $isMahjongTeam && $kategoriId

                ? $this->mahjongTeamService->getMatchmakingHistory($turnamen, $kategoriId)

                : collect(),

            'mahjongTeamAllowedAdvance' => (function () use ($turnamen, $isMahjongTeam, $kategoriId) {
                if (! $turnamen || ! $isMahjongTeam || ! $kategoriId) {
                    return [1];
                }

                $kategoriModel = \App\Models\TurnamenKategori::find($kategoriId);
                $teamCount = $kategoriModel ? (int) $kategoriModel->activeGrup()->count() : 0;

                return app(MahjongTeamSeatingService::class)->allowedAdvanceCounts($teamCount);
            })(),

            'activePlayerCount' => ($isMahjong || $isMahjongTeam) && $turnamen

                ? (($isMahjongTeam
                    ? $grup->sum(fn ($g) => $g->members->count())
                    : $this->mahjongService->getGlobalRankings($turnamen, $kategoriId)->count()))

                : $approvedCount,

        ];

    }

}

