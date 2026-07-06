<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DoublePairingService
{
    public function countApprovedSolos(Turnamen $turnamen): int
    {
        return $this->approvedSoloQuery($turnamen)->count();
    }

    public function getApprovedSolos(Turnamen $turnamen): Collection
    {
        return $this->approvedSoloQuery($turnamen)
            ->with('pemain1')
            ->orderBy('id')
            ->get();
    }

    public function getSummary(Turnamen $turnamen): array
    {
        $approvedIndividuals = $this->countApprovedSolos($turnamen);
        $isEven = $approvedIndividuals % 2 === 0;

        return [
            'approved_individuals' => $approvedIndividuals,
            'is_even' => $isEven,
            'can_auto_pair' => $approvedIndividuals >= 2 && $isEven,
            'pairs_preview' => intdiv($approvedIndividuals, 2),
            'is_paired' => (bool) $turnamen->registration_paired_at,
            'odd_player_warning' => $approvedIndividuals > 0 && ! $isEven,
        ];
    }

    public function assertCanPair(Turnamen $turnamen): void
    {
        if (! $turnamen->isDouble()) {
            throw new RuntimeException('Pemasangan otomatis hanya untuk turnamen double.');
        }

        $count = $this->countApprovedSolos($turnamen);

        if ($count > 0 && $count % 2 !== 0) {
            throw new RuntimeException(sprintf(
                'Jumlah pemain approved ganjil (%d). Tolak satu pemain atau tambahkan pemain baru sebelum menutup pendaftaran.',
                $count
            ));
        }
    }

    /**
     * @return array{pairs_created: int, pairs: array<int, array{peserta_id: int, pemain1: string, pemain2: string}>}
     */
    public function pairApprovedPlayers(Turnamen $turnamen): array
    {
        $this->assertCanPair($turnamen);

        $solos = $this->getApprovedSolos($turnamen);

        if ($solos->isEmpty()) {
            return [
                'pairs_created' => 0,
                'pairs' => [],
            ];
        }

        $shuffled = $solos->shuffle()->values();
        $pairsCreated = 0;
        $pairs = [];

        DB::transaction(function () use ($shuffled, &$pairsCreated, &$pairs) {
            for ($i = 0; $i < $shuffled->count(); $i += 2) {
                /** @var TurnamenPeserta $rowA */
                $rowA = $shuffled[$i];
                /** @var TurnamenPeserta $rowB */
                $rowB = $shuffled[$i + 1];

                $rowA->loadMissing('pemain1');
                $rowB->loadMissing('pemain1');

                $rowA->update([
                    'id_pemain2' => $rowB->id_pemain1,
                    'paired_at' => now(),
                ]);

                $pairs[] = [
                    'peserta_id' => $rowA->id,
                    'pemain1' => $rowA->pemain1->nama,
                    'pemain2' => $rowB->pemain1->nama,
                ];

                $rowB->delete();
                $pairsCreated++;
            }
        });

        return [
            'pairs_created' => $pairsCreated,
            'pairs' => $pairs,
        ];
    }

    protected function approvedSoloQuery(Turnamen $turnamen)
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->soloEntries();
    }
}
