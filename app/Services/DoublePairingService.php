<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenPasangan;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DoublePairingService
{
    public function countApprovedIndividuals(Turnamen $turnamen): int
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->count();
    }

    public function countApprovedSolos(Turnamen $turnamen): int
    {
        return $this->approvedSoloQuery($turnamen)->count();
    }

    public function countApprovedCompletePairs(Turnamen $turnamen): int
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->completePairs()
            ->whereHas('pasanganAsPeserta1.peserta2', function ($query) {
                $query->approved();
            })
            ->count();
    }

    public function countPairedIndividuals(Turnamen $turnamen): int
    {
        return max(0, $this->countApprovedIndividuals($turnamen) - $this->countApprovedSolos($turnamen));
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
        $approvedIndividuals = $this->countApprovedIndividuals($turnamen);
        $approvedSolos = $this->countApprovedSolos($turnamen);
        $pairedIndividuals = max(0, $approvedIndividuals - $approvedSolos);
        $completePairs = $this->countApprovedCompletePairs($turnamen);
        $isEven = $approvedSolos % 2 === 0;

        if ($turnamen->requiresPairRegistration()) {
            return [
                'approved_individuals' => $approvedIndividuals,
                'approved_solos' => $approvedSolos,
                'paired_individuals' => $pairedIndividuals,
                'complete_pairs' => $completePairs,
                'is_even' => $approvedSolos === 0,
                'can_auto_pair' => false,
                'pairs_preview' => $completePairs,
                'is_paired' => (bool) $turnamen->registration_paired_at,
                'odd_player_warning' => $approvedSolos > 0,
                'randomizes_partners' => false,
                'requires_pair_registration' => true,
            ];
        }

        return [
            'approved_individuals' => $approvedIndividuals,
            'approved_solos' => $approvedSolos,
            'paired_individuals' => $pairedIndividuals,
            'complete_pairs' => $completePairs,
            'is_even' => $isEven,
            'can_auto_pair' => $turnamen->randomizesPartners() && $approvedSolos >= 2 && $isEven,
            'pairs_preview' => intdiv($approvedSolos, 2) + $completePairs,
            'is_paired' => (bool) $turnamen->registration_paired_at,
            'odd_player_warning' => $approvedSolos > 0 && ! $isEven,
            'randomizes_partners' => $turnamen->randomizesPartners(),
            'requires_pair_registration' => false,
        ];
    }

    public function assertCanPair(Turnamen $turnamen): void
    {
        if (! $turnamen->randomizesPartners()) {
            throw new RuntimeException('Pemasangan otomatis hanya untuk turnamen single.');
        }

        $count = $this->countApprovedSolos($turnamen);

        if ($count > 0 && $count % 2 !== 0) {
            throw new RuntimeException(sprintf(
                'Jumlah pemain approved ganjil (%d). Tolak satu pemain atau tambahkan pemain baru sebelum menutup pendaftaran.',
                $count
            ));
        }
    }

    public function assertCanCloseWithoutRandomPairing(Turnamen $turnamen): void
    {
        if (! $turnamen->requiresPairRegistration()) {
            return;
        }

        $solos = $this->countApprovedSolos($turnamen);

        if ($solos > 0) {
            throw new RuntimeException(sprintf(
                'Masih ada %d pemain approved tanpa pasangan. Turnamen double wajib mendaftar berpasangan sebelum pendaftaran ditutup.',
                $solos
            ));
        }

        $incomplete = TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->where(function ($query) {
                $query->whereHas('pasanganAsPeserta1.peserta2', function ($partner) {
                    $partner->where('status', '!=', 'approved');
                })->orWhereHas('pasanganAsPeserta2.peserta1', function ($partner) {
                    $partner->where('status', '!=', 'approved');
                });
            })
            ->count();

        if ($incomplete > 0) {
            throw new RuntimeException(
                'Setiap pasangan harus kedua pemainnya berstatus approved sebelum menutup pendaftaran.'
            );
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

        DB::transaction(function () use ($shuffled, $turnamen, &$pairsCreated, &$pairs) {
            for ($i = 0; $i < $shuffled->count(); $i += 2) {
                /** @var TurnamenPeserta $rowA */
                $rowA = $shuffled[$i];
                /** @var TurnamenPeserta $rowB */
                $rowB = $shuffled[$i + 1];

                $rowA->loadMissing('pemain1');
                $rowB->loadMissing('pemain1');

                TurnamenPasangan::create([
                    'id_turnamen' => $turnamen->id,
                    'id_peserta_1' => $rowA->id,
                    'id_peserta_2' => $rowB->id,
                    'paired_at' => now(),
                ]);

                $pairs[] = [
                    'peserta_id' => $rowA->id,
                    'pemain1' => $rowA->pemain1->nama,
                    'pemain2' => $rowB->pemain1->nama,
                ];

                $pairsCreated++;
            }
        });

        return [
            'pairs_created' => $pairsCreated,
            'pairs' => $pairs,
        ];
    }

    public function createPair(Turnamen $turnamen, TurnamenPeserta $peserta1, TurnamenPeserta $peserta2): TurnamenPasangan
    {
        if ((int) $peserta1->id_turnamen !== (int) $turnamen->id
            || (int) $peserta2->id_turnamen !== (int) $turnamen->id) {
            throw new RuntimeException('Kedua peserta harus berada pada turnamen yang sama.');
        }

        if ($peserta1->isPaired() || $peserta2->isPaired()) {
            throw new RuntimeException('Salah satu peserta sudah memiliki pasangan.');
        }

        return TurnamenPasangan::create([
            'id_turnamen' => $turnamen->id,
            'id_peserta_1' => $peserta1->id,
            'id_peserta_2' => $peserta2->id,
            'paired_at' => now(),
        ]);
    }

    protected function approvedSoloQuery(Turnamen $turnamen)
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->soloEntries();
    }
}
