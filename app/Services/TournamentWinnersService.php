<?php

namespace App\Services;

use App\Models\Pemain;
use App\Models\Turnamen;

class TournamentWinnersService
{
    protected $knockoutBracketService;
    protected $photoService;

    public function __construct(KnockoutBracketService $knockoutBracketService, PemainPhotoService $photoService)
    {
        $this->knockoutBracketService = $knockoutBracketService;
        $this->photoService = $photoService;
    }

    public function getWinners(Turnamen $turnamen): array
    {
        if ($turnamen->status !== 'completed') {
            return $this->emptyPayload();
        }

        if ($turnamen->isMahjong()) {
            return $this->fromMahjong($turnamen);
        }

        return $this->fromKnockout($turnamen);
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

    protected function fromMahjong(Turnamen $turnamen): array
    {
        $rows = $turnamen->relationLoaded('pemenang')
            ? $turnamen->pemenang
            : $turnamen->pemenang()->with('pemain')->orderBy('peringkat')->get();

        if ($rows->isEmpty()) {
            return $this->emptyPayload();
        }

        $payload = $this->emptyPayload();

        foreach ($rows as $row) {
            $slot = $this->slotForRank((int) $row->peringkat);

            if (! $slot || ! $row->pemain) {
                continue;
            }

            $payload[$slot] = $this->formatPemainEntry($row->pemain);
        }

        $payload['has_winners'] = $payload['first'] || $payload['second'] || $payload['third'];

        return $payload;
    }

    protected function fromKnockout(Turnamen $turnamen): array
    {
        $bracket = $this->knockoutBracketService->getBracketTree($turnamen);

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

    protected function formatPemainEntry(Pemain $pemain): array
    {
        return [
            'label' => $pemain->nama,
            'players' => [[
                'id' => (int) $pemain->id,
                'nama' => $pemain->nama,
                'foto_url' => $this->photoService->url($pemain->foto),
            ]],
        ];
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
