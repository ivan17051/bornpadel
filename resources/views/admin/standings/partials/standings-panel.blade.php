@if ($turnamen->isMahjong())
    <x-mahjong-leaderboard
        :standings="$standings"
        :turnamen="$turnamen"
        :overall="$mahjongOverall ?? collect()"
        :refreshable="true"
    />
@else
    <x-group-leaderboard
        :standings="$standings"
        :turnamen="$turnamen"
        :refreshable="true"
    />
@endif
