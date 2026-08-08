@if ($turnamen->isMahjong())
    <x-mahjong-leaderboard
        :standings="$standings"
        :turnamen="$turnamen"
        :refreshable="true"
    />
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
