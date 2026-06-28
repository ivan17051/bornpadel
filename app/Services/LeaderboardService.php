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
                $query->with(['pemain', 'turnamenPeserta.pemain1', 'turnamenPeserta.pemain2'])
                    ->orderByDesc('poin_didapat')
                    ->orderByDesc('set_menang')
                    ->orderByDesc('games_menang');
            }])
            ->orderBy('nama')
            ->get()
            ->map(function (Grup $grup) use ($turnamen) {
                return [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'is_double' => $turnamen->isDouble(),
                    'standings' => $grup->members->values()->map(function ($member, $index) {
                        return [
                            'rank' => $index + 1,
                            'id_pemain' => $member->id_pemain,
                            'id_peserta' => $member->id_turnamen_peserta,
                            'pemain_ids' => $this->resolveStandingPemainIds($member),
                            'nama' => $member->display_name,
                            'poin_didapat' => $member->poin_didapat,
                            'set_menang' => $member->set_menang,
                            'games_menang' => $member->games_menang,
                        ];
                    }),
                ];
            });
    }

    protected function resolveStandingPemainIds($member): array
    {
        if ($member->turnamenPeserta) {
            return $member->turnamenPeserta->pemainIds();
        }

        return $member->id_pemain ? [(int) $member->id_pemain] : [];
    }
}
