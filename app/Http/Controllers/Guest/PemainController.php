<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Pemain;
use App\Models\TurnamenPeserta;

class PemainController extends Controller
{
    public function show(Pemain $pemain)
    {
        $pemain->loadMissing([]);

        $tournamentHistory = TurnamenPeserta::query()
            ->involvingPemain($pemain->id)
            ->with(['turnamen', 'pemain1', 'pasanganAsPeserta1.peserta2.pemain1', 'pasanganAsPeserta2.peserta1.pemain1'])
            ->latest()
            ->get()
            ->map(function (TurnamenPeserta $peserta) use ($pemain) {
                return [
                    'turnamen' => $peserta->turnamen,
                    'status' => $peserta->status,
                    'status_label' => $peserta->status_label,
                    'partner' => (int) $peserta->id_pemain1 === (int) $pemain->id
                        ? $peserta->partner_pemain
                        : $peserta->pemain1,
                    'registered_at' => $peserta->created_at,
                ];
            });

        return view('guest.pemain.show', compact('pemain', 'tournamentHistory'));
    }
}
