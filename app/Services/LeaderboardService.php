<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
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

        $grupQuery = $turnamen->isMahjong()
            ? $turnamen->activeGrup()
            : $turnamen->grup();

        return $grupQuery
            ->with(['members' => function ($query) use ($turnamen) {
                $query->with(['pemain', 'turnamenPeserta.pemain1', 'turnamenPeserta.pemain2']);

                if ($turnamen->isMahjong()) {
                    $query->orderByDesc('poin_akumulasi')
                        ->orderByDesc('poin_didapat');
                } else {
                    $query->orderByDesc('poin_didapat')
                        ->orderByDesc('set_menang')
                        ->orderByDesc('games_menang');
                }
            }])
            ->orderBy('nama')
            ->get()
            ->map(function (Grup $grup) use ($turnamen) {
                return [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'babak' => $grup->babak,
                    'is_double' => $turnamen->isDouble(),
                    'is_mahjong' => $turnamen->isMahjong(),
                    'standings' => $grup->members->values()->map(function ($member, $index) use ($turnamen) {
                        $row = [
                            'rank' => $index + 1,
                            'id_pemain' => $member->id_pemain,
                            'id_peserta' => $member->id_turnamen_peserta,
                            'pemain_ids' => $this->resolveStandingPemainIds($member),
                            'nama' => $member->display_name,
                            'poin_didapat' => $member->poin_didapat,
                            'set_menang' => $member->set_menang,
                            'games_menang' => $member->games_menang,
                        ];

                        if ($turnamen->isMahjong()) {
                            $row['poin_akumulasi'] = (int) $member->poin_akumulasi;
                            $row['total_poin'] = $member->total_poin;
                        }

                        return $row;
                    }),
                ];
            });
    }

    public function getMahjongGlobalStandings(?int $turnamenId = null): Collection
    {
        $turnamen = $turnamenId
            ? Turnamen::find($turnamenId)
            : $this->getActiveTournament();

        if (! $turnamen || ! $turnamen->isMahjong()) {
            return collect();
        }

        $members = $this->collectMahjongStandingMembers($turnamen);

        if ($members->isNotEmpty()) {
            return $members
                ->sortByDesc(function (GrupMember $member) {
                    return $member->total_poin;
                })
                ->values()
                ->map(function (GrupMember $member, $index) {
                    return $this->formatMahjongStandingRow($member, $index + 1);
                });
        }

        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->with('pemain1')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (TurnamenPeserta $peserta, $index) {
                return [
                    'rank' => $index + 1,
                    'id_pemain' => $peserta->id_pemain1,
                    'id_peserta' => $peserta->id,
                    'pemain_ids' => $peserta->pemainIds(),
                    'nama' => $peserta->display_name,
                    'grup_nama' => null,
                    'poin_akumulasi' => 0,
                    'poin_didapat' => 0,
                    'total_poin' => 0,
                ];
            });
    }

    protected function collectMahjongStandingMembers(Turnamen $turnamen): Collection
    {
        $activeMembers = GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
            })
            ->with(['pemain', 'turnamenPeserta.pemain1', 'turnamenPeserta.pemain2', 'grup'])
            ->get();

        if ($activeMembers->isNotEmpty()) {
            return $activeMembers;
        }

        $latestBabak = $turnamen->grup()->max('babak');

        if (! $latestBabak) {
            return collect();
        }

        return GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen, $latestBabak) {
                $query->where('id_turnamen', $turnamen->id)->where('babak', $latestBabak);
            })
            ->with(['pemain', 'turnamenPeserta.pemain1', 'turnamenPeserta.pemain2', 'grup'])
            ->get();
    }

    protected function formatMahjongStandingRow(GrupMember $member, int $rank): array
    {
        return [
            'rank' => $rank,
            'id_pemain' => $member->id_pemain,
            'id_peserta' => $member->id_turnamen_peserta,
            'pemain_ids' => $this->resolveStandingPemainIds($member),
            'nama' => $member->display_name,
            'grup_nama' => optional($member->grup)->nama,
            'poin_akumulasi' => (int) $member->poin_akumulasi,
            'poin_didapat' => (int) $member->poin_didapat,
            'total_poin' => $member->total_poin,
        ];
    }

    protected function resolveStandingPemainIds($member): array
    {
        if ($member->turnamenPeserta) {
            return $member->turnamenPeserta->pemainIds();
        }

        return $member->id_pemain ? [(int) $member->id_pemain] : [];
    }
}
