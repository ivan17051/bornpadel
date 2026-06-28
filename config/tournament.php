<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lifetime ranking points (m_pemain.total_poin)
    |--------------------------------------------------------------------------
    */
    'points' => [
        'match_win' => (int) env('TOURNAMENT_POINTS_MATCH_WIN', 10),

        'placement' => [
            1 => (int) env('TOURNAMENT_POINTS_FIRST', 100),
            2 => (int) env('TOURNAMENT_POINTS_SECOND', 50),
            3 => (int) env('TOURNAMENT_POINTS_THIRD', 25),
        ],
    ],

];
