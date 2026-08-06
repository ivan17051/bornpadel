<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenPasangan;
use App\Models\TurnamenPeserta;
use App\Services\Concerns\ResolvesTurnamenKategori;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DoublePairingService
{
    use ResolvesTurnamenKategori;

    public function countApprovedIndividuals(Turnamen $turnamen, $idKategori = null): int
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return TurnamenPeserta::query()
            ->forKategori($kategori->id)
            ->approved()
            ->count();
    }

    public function countApprovedSolos(Turnamen $turnamen, $idKategori = null): int
    {
        return $this->approvedSoloQuery($turnamen, $idKategori)->count();
    }

    public function countApprovedCompletePairs(Turnamen $turnamen, $idKategori = null): int
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return TurnamenPeserta::query()
            ->forKategori($kategori->id)
            ->approved()
            ->completePairs()
            ->whereHas('pasanganAsPeserta1.peserta2', function ($query) {
                $query->approved();
            })
            ->count();
    }

    public function countPairedIndividuals(Turnamen $turnamen, $idKategori = null): int
    {
        return max(0, $this->countApprovedIndividuals($turnamen, $idKategori) - $this->countApprovedSolos($turnamen, $idKategori));
    }

    public function getApprovedSolos(Turnamen $turnamen, $idKategori = null): Collection
    {
        return $this->approvedSoloQuery($turnamen, $idKategori)
            ->with('pemain1')
            ->orderBy('id')
            ->get();
    }

    public function getSummary(Turnamen $turnamen, $idKategori = null): array
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $approvedIndividuals = $this->countApprovedIndividuals($turnamen, $kategori->id);
        $approvedSolos = $this->countApprovedSolos($turnamen, $kategori->id);
        $pairedIndividuals = max(0, $approvedIndividuals - $approvedSolos);
        $completePairs = $this->countApprovedCompletePairs($turnamen, $kategori->id);
        $isEven = $approvedSolos % 2 === 0;
        $isPaired = (bool) ($kategori->registration_paired_at ?? $turnamen->registration_paired_at);

        if ($turnamen->requiresPairRegistration()) {
            return [
                'approved_individuals' => $approvedIndividuals,
                'approved_solos' => $approvedSolos,
                'paired_individuals' => $pairedIndividuals,
                'complete_pairs' => $completePairs,
                'is_even' => $approvedSolos === 0,
                'can_auto_pair' => false,
                'pairs_preview' => $completePairs,
                'is_paired' => $isPaired,
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
            'is_paired' => $isPaired,
            'odd_player_warning' => $approvedSolos > 0 && ! $isEven,
            'randomizes_partners' => $turnamen->randomizesPartners(),
            'requires_pair_registration' => false,
        ];
    }

    public function assertCanPair(Turnamen $turnamen, $idKategori = null): void
    {
        if (! $turnamen->randomizesPartners()) {
            throw new RuntimeException('Pemasangan otomatis hanya untuk turnamen single.');
        }

        $count = $this->countApprovedSolos($turnamen, $idKategori);

        if ($count > 0 && $count % 2 !== 0) {
            throw new RuntimeException(sprintf(
                'Jumlah pemain approved ganjil (%d). Tolak satu pemain atau tambahkan pemain baru sebelum menutup pendaftaran.',
                $count
            ));
        }
    }

    public function assertCanCloseWithoutRandomPairing(Turnamen $turnamen, $idKategori = null): void
    {
        if (! $turnamen->requiresPairRegistration()) {
            return;
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $solos = $this->countApprovedSolos($turnamen, $kategori->id);

        if ($solos > 0) {
            throw new RuntimeException(sprintf(
                'Masih ada %d pemain approved tanpa pasangan. Turnamen double wajib mendaftar berpasangan sebelum pendaftaran ditutup.',
                $solos
            ));
        }

        $incomplete = TurnamenPeserta::query()
            ->forKategori($kategori->id)
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
    public function pairApprovedPlayers(Turnamen $turnamen, $idKategori = null): array
    {
        $this->assertCanPair($turnamen, $idKategori);
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        $solos = $this->getApprovedSolos($turnamen, $kategori->id);

        if ($solos->isEmpty()) {
            return [
                'pairs_created' => 0,
                'pairs' => [],
            ];
        }

        $shuffled = $solos->shuffle()->values();
        $pairsCreated = 0;
        $pairs = [];

        DB::transaction(function () use ($shuffled, $turnamen, $kategori, &$pairsCreated, &$pairs) {
            for ($i = 0; $i < $shuffled->count(); $i += 2) {
                /** @var TurnamenPeserta $rowA */
                $rowA = $shuffled[$i];
                /** @var TurnamenPeserta $rowB */
                $rowB = $shuffled[$i + 1];

                $rowA->loadMissing('pemain1');
                $rowB->loadMissing('pemain1');

                TurnamenPasangan::create([
                    'id_turnamen' => $turnamen->id,
                    'id_kategori' => $kategori->id,
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

    public function createPair(Turnamen $turnamen, TurnamenPeserta $peserta1, TurnamenPeserta $peserta2, $idKategori = null): TurnamenPasangan
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if ((int) $peserta1->id_kategori !== (int) $kategori->id
            || (int) $peserta2->id_kategori !== (int) $kategori->id) {
            throw new RuntimeException('Kedua peserta harus berada pada kategori yang sama.');
        }

        if ($peserta1->isPaired() || $peserta2->isPaired()) {
            throw new RuntimeException('Salah satu peserta sudah memiliki pasangan.');
        }

        return TurnamenPasangan::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $kategori->id,
            'id_peserta_1' => $peserta1->id,
            'id_peserta_2' => $peserta2->id,
            'paired_at' => now(),
        ]);
    }

    protected function approvedSoloQuery(Turnamen $turnamen, $idKategori = null)
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return TurnamenPeserta::query()
            ->forKategori($kategori->id)
            ->approved()
            ->soloEntries();
    }
}
