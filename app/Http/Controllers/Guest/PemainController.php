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
            ->where(function ($query) use ($pemain) {
                $query->where('id_pemain1', $pemain->id)
                    ->orWhere('id_pemain2', $pemain->id);
            })
            ->with(['turnamen', 'pemain1', 'pemain2'])
            ->latest()
            ->get()
            ->map(function (TurnamenPeserta $peserta) use ($pemain) {
                $partner = null;

                if ((int) $peserta->id_pemain1 === (int) $pemain->id) {
                    $partner = $peserta->pemain2;
                } elseif ((int) $peserta->id_pemain2 === (int) $pemain->id) {
                    $partner = $peserta->pemain1;
                }

                return [
                    'turnamen' => $peserta->turnamen,
                    'status' => $peserta->status,
                    'partner' => $partner,
                    'registered_at' => $peserta->created_at,
                ];
            });

        return view('guest.pemain.show', compact('pemain', 'tournamentHistory'));
    }
}
