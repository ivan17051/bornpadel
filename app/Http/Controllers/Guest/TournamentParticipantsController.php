<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Turnamen;
use App\Services\PemainRegistrationService;
use Illuminate\Http\Request;

class TournamentParticipantsController extends Controller
{
    public function show(Request $request, PemainRegistrationService $registrationService)
    {
        $turnamen = $registrationService->resolvePublicTournament(
            $request->filled('id_turnamen') ? (int) $request->id_turnamen : null
        );

        if (! $turnamen) {
            return redirect()->route('guest.landing')
                ->with('warning', 'Turnamen tidak ditemukan atau tidak tersedia.');
        }

        $participantData = $registrationService->getPublicParticipantList($turnamen);

        return view('guest.participants', [
            'turnamen' => $turnamen,
            'participantType' => $participantData['type'],
            'participants' => $participantData['items'],
        ]);
    }
}
