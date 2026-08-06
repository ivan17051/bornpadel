<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\Concerns\ResolvesTurnamenKategori;
use RuntimeException;

class TournamentCapacityService
{
    use ResolvesTurnamenKategori;

    public function countApprovedParticipants(Turnamen $turnamen, $idKategori = null): int
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        return TurnamenPeserta::query()
            ->forKategori($kategori->id)
            ->approved()
            ->count();
    }

    public function remainingApprovalSlots(Turnamen $turnamen, $idKategori = null): ?int
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $limit = $kategori->maks_peserta;

        if ($limit === null) {
            return null;
        }

        return max(0, (int) $limit - $this->countApprovedParticipants($turnamen, $kategori->id));
    }

    public function canApprove(Turnamen $turnamen, int $additionalApprovals = 1, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if ($kategori->maks_peserta === null) {
            return true;
        }

        if ($additionalApprovals <= 0) {
            return true;
        }

        return $this->countApprovedParticipants($turnamen, $kategori->id) + $additionalApprovals
            <= (int) $kategori->maks_peserta;
    }

    public function assertCanApprove(Turnamen $turnamen, int $additionalApprovals = 1, $idKategori = null): void
    {
        if ($this->canApprove($turnamen, $additionalApprovals, $idKategori)) {
            return;
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $approved = $this->countApprovedParticipants($turnamen, $kategori->id);
        $limit = (int) $kategori->maks_peserta;

        throw new RuntimeException(sprintf(
            'Kapasitas kategori penuh. Maksimal %d peserta disetujui. Saat ini %d disetujui, tidak dapat menyetujui %d lagi.',
            $limit,
            $approved,
            $additionalApprovals
        ));
    }

    /**
     * @param  iterable<TurnamenPeserta>  $pesertaRows
     */
    public function countNewApprovalsNeeded(iterable $pesertaRows): int
    {
        $count = 0;

        foreach ($pesertaRows as $peserta) {
            if ($peserta->status !== 'approved') {
                $count++;
            }
        }

        return $count;
    }
}
