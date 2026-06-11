<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pemain;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GroupMatchmakingService
{
    const PLAYERS_PER_GROUP = 4;

    public function getActiveTournament(): ?Turnamen
    {
        return Turnamen::whereIn('status', ['open', 'ongoing'])
            ->latest('doc')
            ->first();
    }

    public function resolveTournament(?int $id = null): ?Turnamen
    {
        if ($id) {
            return Turnamen::find($id);
        }

        return $this->getActiveTournament();
    }

    public function listForFilter(): Collection
    {
        return Turnamen::query()->orderByDesc('doc')->get();
    }

    public function canCloseRegistration(Turnamen $turnamen): bool
    {
        return $turnamen->status === 'open';
    }

    public function closeRegistration(Turnamen $turnamen): Turnamen
    {
        if (! $this->canCloseRegistration($turnamen)) {
            throw new RuntimeException('Pendaftaran sudah ditutup atau turnamen belum dibuka.');
        }

        $turnamen->update(['status' => 'ongoing']);

        return $turnamen->fresh();
    }

    public function canGenerateRandomGroups(Turnamen $turnamen): bool
    {
        return $turnamen->status === 'ongoing'
            && ! $turnamen->grup()->exists();
    }

    public function getApprovedPlayers(Turnamen $turnamen): Collection
    {
        return Pemain::whereHas('turnamenPeserta', function ($query) use ($turnamen) {
            $query->where('id_turnamen', $turnamen->id)->where('status', 'approved');
        })->orderBy('nama')->get();
    }

    public function countApprovedPlayers(Turnamen $turnamen): int
    {
        return TurnamenPeserta::where('id_turnamen', $turnamen->id)
            ->where('status', 'approved')
            ->count();
    }

    public function generateRandomGroups(Turnamen $turnamen, int $playersPerGroup = self::PLAYERS_PER_GROUP): array
    {
        if ($turnamen->status === 'open') {
            throw new RuntimeException('Pendaftaran masih dibuka. Tutup pendaftaran terlebih dahulu.');
        }

        if ($turnamen->status === 'draft' || $turnamen->status === 'completed') {
            throw new RuntimeException('Turnamen tidak dalam status yang valid untuk pembagian grup.');
        }

        if ($turnamen->grup()->exists()) {
            throw new RuntimeException('Grup sudah dibuat untuk turnamen ini.');
        }

        $players = $this->getApprovedPlayers($turnamen);

        if ($players->count() < 2) {
            throw new RuntimeException('Minimal 2 pemain dengan status approved diperlukan.');
        }

        return DB::transaction(function () use ($turnamen, $players, $playersPerGroup) {
            $shuffled = $players->shuffle()->values();
            $chunks = $shuffled->chunk($playersPerGroup);
            $result = ['groups' => [], 'matches' => 0];

            foreach ($chunks as $index => $groupPlayers) {
                $grup = Grup::create([
                    'id_turnamen' => $turnamen->id,
                    'nama' => 'Grup ' . $this->groupLabel($index + 1),
                ]);

                foreach ($groupPlayers as $pemain) {
                    GrupMember::create([
                        'id_grup' => $grup->id,
                        'id_pemain' => $pemain->id,
                    ]);
                }

                $matchCount = $this->generateRoundRobinMatches($turnamen, $grup, $groupPlayers);
                $result['matches'] += $matchCount;
                $result['groups'][] = [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'pemain_count' => $groupPlayers->count(),
                    'matches' => $matchCount,
                ];
            }

            return $result;
        });
    }

    protected function generateRoundRobinMatches(Turnamen $turnamen, Grup $grup, Collection $players): int
    {
        $playerIds = $players->pluck('id')->values()->all();
        $count = 0;

        for ($i = 0; $i < count($playerIds); $i++) {
            for ($j = $i + 1; $j < count($playerIds); $j++) {
                Pertandingan::create([
                    'id_turnamen' => $turnamen->id,
                    'id_grup' => $grup->id,
                    'nama_ronde' => 'Fase Grup',
                    'id_pemain1' => $playerIds[$i],
                    'id_pemain2' => $playerIds[$j],
                    'status' => 'scheduled',
                ]);
                $count++;
            }
        }

        return $count;
    }

    protected function groupLabel(int $index): string
    {
        return chr(64 + $index);
    }
}
