<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPublicKategori;
use App\Services\PemainRegistrationService;
use Illuminate\Http\Request;

class TournamentParticipantsController extends Controller
{
    use ResolvesPublicKategori;

    public function show(Request $request, PemainRegistrationService $registrationService)
    {
        $turnamen = $registrationService->resolvePublicTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Turnamen tidak ditemukan atau tidak tersedia.');
        }

        $turnamen->loadMissing('kategori');
        $kategoriList = $turnamen->kategori->sortBy([['urutan', 'asc'], ['id', 'asc']])->values();
        $kategori = $this->resolvePublicKategori($turnamen, $this->requestKategoriId($request))
            ?? $turnamen->defaultKategori();

        $participantData = $registrationService->getPublicParticipantList($turnamen, $kategori ? $kategori->id : null);

        return view('guest.participants', [
            'turnamen' => $turnamen,
            'kategori' => $kategori,
            'kategoriList' => $kategoriList,
            'participantType' => $participantData['type'],
            'participants' => $participantData['items'],
        ]);
    }
}
