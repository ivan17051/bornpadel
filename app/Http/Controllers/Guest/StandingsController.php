<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPublicKategori;
use App\Services\LeaderboardService;
use App\Services\PemainRegistrationService;
use App\Services\TournamentWinnersService;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    use ResolvesPublicKategori;

    public function index(
        Request $request,
        LeaderboardService $leaderboardService,
        PemainRegistrationService $registrationService,
        TournamentWinnersService $winnersService
    ) {
        $turnamen = $registrationService->resolvePublicTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        $kategori = null;
        $kategoriList = collect();
        $kategoriId = null;

        if ($turnamen) {
            $turnamen->loadMissing('kategori');
            $kategoriList = $turnamen->kategori->sortBy([['urutan', 'asc'], ['id', 'asc']])->values();
            $kategori = $this->resolvePublicKategori($turnamen, $this->requestKategoriId($request))
                ?? $turnamen->defaultKategori();
            $kategoriId = $kategori ? $kategori->id : null;
        }

        $mahjongStandings = $turnamen && $turnamen->isMahjong()
            ? $leaderboardService->getMahjongStandingsByBabak($turnamen->id, $kategoriId)
            : ['sections' => collect(), 'recap' => collect(), 'babak_numbers' => collect()];
        $standings = $turnamen && $turnamen->isMahjong()
            ? $mahjongStandings['sections']
            : $leaderboardService->getStandings(optional($turnamen)->id, $kategoriId);

        $winners = $turnamen && $turnamen->isMahjong()
            && (($kategori && $kategori->status === 'completed') || $turnamen->status === 'completed')
            ? $winnersService->getWinners($turnamen, $kategoriId)
            : null;

        $postLeagueRanking = $turnamen && $turnamen->usesKnockoutBracket()
            ? $leaderboardService->getPostLeagueRanking($turnamen->id, $kategoriId)
            : ['sections' => collect(), 'has_bracket' => false, 'is_double' => false];

        return view('guest.standings', compact(
            'turnamen',
            'kategori',
            'kategoriList',
            'standings',
            'winners',
            'postLeagueRanking'
        ));
    }
}
