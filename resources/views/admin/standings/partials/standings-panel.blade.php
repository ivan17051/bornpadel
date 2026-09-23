@if ($turnamen->isMahjong())
    <x-mahjong-leaderboard
        :standings="$standings"
        :turnamen="$turnamen"
        :refreshable="true"
    />
    @include('admin.matchmaking.partials.mahjong-history', [
        'mahjongHistory' => $mahjongHistory ?? collect(),
        'idPrefix' => 'klasemen-mahjong-history',
        'linkPemain' => true,
        'cardClass' => 'card mb-0 mt-4',
    ])
@elseif ($turnamen->isMahjongTeam())
    <x-mahjong-team-leaderboard
        :standings="$standings"
        :turnamen="$turnamen"
        :refreshable="true"
    />
    @include('admin.matchmaking.partials.mahjong-team-history', [
        'mahjongTeamHistory' => $mahjongTeamHistory ?? collect(),
        'idPrefix' => 'klasemen-mahjong-team-history',
        'linkPemain' => true,
        'cardClass' => 'card mb-0 mt-4',
    ])
@elseif ($turnamen->isFriendly())
    <x-friendly-leaderboard
        :standings="$standings"
        :turnamen="$turnamen"
        :refreshable="true"
        :match-sessions="$friendlyMatchSessions ?? collect()"
    />
@else
    <x-group-leaderboard
        :standings="$standings"
        :turnamen="$turnamen"
        :refreshable="true"
    />
@endif
