<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPublicKategori;
use App\Services\KnockoutBracketService;
use App\Services\PemainRegistrationService;
use Illuminate\Http\Request;

class BracketController extends Controller
{
    use ResolvesPublicKategori;

    public function index(
        Request $request,
        KnockoutBracketService $bracketService,
        PemainRegistrationService $registrationService
    ) {
        $turnamen = $registrationService->resolvePublicTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        $kategori = null;
        $kategoriList = collect();
        $bracket = [];

        if ($turnamen) {
            $turnamen->loadMissing('kategori');
            $kategoriList = $turnamen->kategori->sortBy([['urutan', 'asc'], ['id', 'asc']])->values();
            $kategori = $this->resolvePublicKategori($turnamen, $this->requestKategoriId($request))
                ?? $turnamen->defaultKategori();
            $bracket = $bracketService->getBracketTree($turnamen, $kategori ? $kategori->id : null);
        }

        return view('guest.bracket', compact('turnamen', 'kategori', 'kategoriList', 'bracket'));
    }
}
