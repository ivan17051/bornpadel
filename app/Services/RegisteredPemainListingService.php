<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Http\Request;

class RegisteredPemainListingService
{
    public function paginate(Request $request, ?Turnamen $turnamen): array
    {
        $isDoubleView = $turnamen && $turnamen->isDouble() && $turnamen->isRegistrationClosed();

        if (! $turnamen) {
            return [
                'pemain' => Pemain::query()->whereRaw('0 = 1')->paginate(15)->withQueryString(),
                'peserta' => null,
                'isDoubleView' => false,
            ];
        }

        if ($isDoubleView) {
            $pesertaQuery = TurnamenPeserta::query()
                ->forTurnamen($turnamen->id)
                ->with(['pemain1', 'pemain2'])
                ->latest();

            if ($request->filled('status')) {
                $pesertaQuery->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $pesertaQuery->where(function ($builder) use ($search) {
                    $builder->whereHas('pemain1', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%");
                    })->orWhereHas('pemain2', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%");
                    });
                });
            }

            return [
                'pemain' => null,
                'peserta' => $pesertaQuery->paginate(15)->withQueryString(),
                'isDoubleView' => true,
            ];
        }

        $query = Pemain::query()->latest();

        $query->where(function ($builder) use ($turnamen, $request) {
            $builder->whereHas('turnamenPesertaAsPemain1', function ($q) use ($turnamen, $request) {
                $q->where('id_turnamen', $turnamen->id);
                if ($request->filled('status')) {
                    $q->where('status', $request->status);
                }
            })->orWhereHas('turnamenPesertaAsPemain2', function ($q) use ($turnamen, $request) {
                $q->where('id_turnamen', $turnamen->id);
                if ($request->filled('status')) {
                    $q->where('status', $request->status);
                }
            });
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        return [
            'pemain' => $query->paginate(15)->withQueryString(),
            'peserta' => null,
            'isDoubleView' => false,
        ];
    }
}
