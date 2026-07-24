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
    />
@else
    <x-group-leaderboard
        :standings="$standings"
        :turnamen="$turnamen"
        :refreshable="true"
    />
@endif
