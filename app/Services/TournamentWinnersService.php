<?php

namespace App\Services;

use App\Models\GrupMember;
use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use Illuminate\Support\Collection;

class TournamentWinnersService
{
    protected $knockoutBracketService;
    protected $photoService;

    public function __construct(KnockoutBracketService $knockoutBracketService, PemainPhotoService $photoService)
    {
        $this->knockoutBracketService = $knockoutBracketService;
        $this->photoService = $photoService;
    }

    public function getWinners(Turnamen $turnamen, $idKategori = null): array
    {
        $kategori = $turnamen->resolveKategori($idKategori);

        if ($kategori->status !== 'completed' && $turnamen->status !== 'completed') {
            return $this->emptyPayload();
        }

        if ($turnamen->isMahjong() || $turnamen->isMahjongTeam()) {
            return $this->fromMahjong($turnamen, $kategori);
        }

        return $this->fromKnockout($turnamen, $kategori);
    }

    protected function emptyPayload(): array
    {
        return [
            'has_winners' => false,
            'first' => null,
            'second' => null,
            'third' => null,
        ];
    }

    protected function fromMahjong(Turnamen $turnamen, TurnamenKategori $kategori): array
    {
        $rows = $kategori->pemenang()->with('pemain')->orderBy('peringkat')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return $this->emptyPayload();
        }

        $teamNames = $turnamen->isMahjongTeam()
            ? $this->teamNamesForWinnerRows($kategori, $rows)
            : [];
        $payload = $this->emptyPayload();

        foreach ($rows->groupBy('peringkat') as $rank => $rankRows) {
            $slot = $this->slotForRank((int) $rank);

            if (! $slot) {
                continue;
            }

            $players = [];
            $labels = [];

            foreach ($rankRows as $row) {
                if (! $row->pemain) {
                    continue;
                }

                $players[] = [
                    'id' => (int) $row->pemain->id,
                    'nama' => $row->pemain->nama,
                    'foto_url' => $this->photoService->url($row->pemain->foto),
                ];

                $pesertaId = $row->id_turnamen_peserta ? (int) $row->id_turnamen_peserta : null;
                if ($pesertaId && ! empty($teamNames[$pesertaId])) {
                    $labels[] = $teamNames[$pesertaId];
                }
            }

            if ($players === []) {
                continue;
            }

            $uniqueTeamNames = array_values(array_unique($labels));
            $label = count($uniqueTeamNames) === 1
                ? $uniqueTeamNames[0]
                : (count($players) === 1
                    ? $players[0]['nama']
                    : implode(' / ', array_column($players, 'nama')));

            $payload[$slot] = [
                'label' => $label,
                'players' => $players,
            ];
        }

        $payload['has_winners'] = $payload['first'] || $payload['second'] || $payload['third'];

        return $payload;
    }

    /**
     * @param  Collection<int, \App\Models\TurnamenPemenang>  $rows
     * @return array<int, string>
     */
    protected function teamNamesForWinnerRows(TurnamenKategori $kategori, Collection $rows): array
    {
        $pesertaIds = $rows->pluck('id_turnamen_peserta')->filter()->map(function ($id) {
            return (int) $id;
        })->unique()->values();

        if ($pesertaIds->isEmpty()) {
            return [];
        }

        return GrupMember::query()
            ->whereIn('id_turnamen_peserta', $pesertaIds->all())
            ->whereHas('grup', function ($query) use ($kategori) {
                $query->where('id_kategori', $kategori->id);
            })
            ->with('grup')
            ->get()
            ->filter(function (GrupMember $member) {
                return (string) optional($member->grup)->nama !== '';
            })
            ->mapWithKeys(function (GrupMember $member) {
                return [(int) $member->id_turnamen_peserta => $member->grup->nama];
            })
            ->all();
    }

    protected function fromKnockout(Turnamen $turnamen, TurnamenKategori $kategori): array
    {
        $bracket = $this->knockoutBracketService->getBracketTree($turnamen, $kategori->id);

        if ($bracket === []) {
            return $this->emptyPayload();
        }

        $finalRound = collect($bracket)->firstWhere('nama_ronde', 'Final');
        $finalMatch = $finalRound
            ? collect($finalRound['matches'])->first(fn ($match) => empty($match['is_third_place']))
            : null;
        $thirdMatch = collect($bracket)
            ->flatMap(fn ($round) => $round['matches'])
            ->firstWhere('is_third_place', true);

        $payload = $this->emptyPayload();
        $payload['first'] = $this->formatBracketEntry(
            $finalMatch['pemenang'] ?? null,
            $finalMatch['pemenang_players'] ?? []
        );
        $payload['second'] = $this->formatBracketEntry(
            $finalMatch['runner_up'] ?? null,
            $finalMatch['runner_up_players'] ?? []
        );
        $payload['third'] = $this->formatBracketEntry(
            $thirdMatch['pemenang'] ?? null,
            $thirdMatch['pemenang_players'] ?? []
        );
        $payload['has_winners'] = $payload['first'] || $payload['second'] || $payload['third'];

        return $payload;
    }

    protected function slotForRank(int $rank): ?string
    {
        return [
            1 => 'first',
            2 => 'second',
            3 => 'third',
        ][$rank] ?? null;
    }

    /**
     * @param  array<int, array{id: int, nama: string, foto_url: string}>  $players
     */
    protected function formatBracketEntry(?string $label, array $players): ?array
    {
        if (! $label) {
            return null;
        }

        return [
            'label' => $label,
            'players' => array_values($players),
        ];
    }
}
