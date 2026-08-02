<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Pemain;
use App\Models\TurnamenPeserta;
use App\Services\PemainMatchStatsService;

class PemainController extends Controller
{
    public function show(Pemain $pemain, PemainMatchStatsService $matchStats)
    {
        $pemain->loadMissing([]);

        $careerStats = $matchStats->getCareerStats($pemain);

        $tournamentHistory = TurnamenPeserta::query()
            ->where('id_pemain1', $pemain->id)
            ->with(['turnamen', 'pemain1', 'pasanganAsPeserta1.peserta2.pemain1', 'pasanganAsPeserta2.peserta1.pemain1'])
            ->latest()
            ->get()
            ->map(function (TurnamenPeserta $peserta) {
                return [
                    'turnamen' => $peserta->turnamen,
                    'status' => $peserta->status,
                    'status_label' => $peserta->status_label,
                    'partner' => $peserta->partner_pemain,
                    'registered_at' => $peserta->created_at,
                ];
            });

        return view('guest.pemain.show', compact('pemain', 'tournamentHistory', 'careerStats'));
    }
}
