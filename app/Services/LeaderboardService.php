<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\Turnamen;
use Illuminate\Support\Collection;

class LeaderboardService
{
    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::whereIn('status', ['open', 'ongoing', 'completed'])
            ->latest('doc')
            ->first();
    }

    public function getStandings(?int $turnamenId = null): Collection
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen) {
            return collect();
        }

        return $turnamen->grup()
            ->with(['members' => function ($query) {
                $query->with('pemain')
                    ->orderByDesc('poin_didapat')
                    ->orderByDesc('set_menang')
                    ->orderByDesc('games_menang');
            }])
            ->orderBy('nama')
            ->get()
            ->map(function (Grup $grup) {
                return [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'standings' => $grup->members->values()->map(function ($member, $index) {
                        return [
                            'rank' => $index + 1,
                            'id_pemain' => $member->id_pemain,
                            'nama' => $member->pemain->nama ?? '-',
                            'poin_didapat' => $member->poin_didapat,
                            'set_menang' => $member->set_menang,
                            'games_menang' => $member->games_menang,
                        ];
                    }),
                ];
            });
    }
}
