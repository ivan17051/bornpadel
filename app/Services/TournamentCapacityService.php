<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use RuntimeException;

class TournamentCapacityService
{
    public function countApprovedParticipants(Turnamen $turnamen): int
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->count();
    }

    public function remainingApprovalSlots(Turnamen $turnamen): ?int
    {
        if ($turnamen->maks_peserta === null) {
            return null;
        }

        return max(0, (int) $turnamen->maks_peserta - $this->countApprovedParticipants($turnamen));
    }

    public function canApprove(Turnamen $turnamen, int $additionalApprovals = 1): bool
    {
        if ($turnamen->maks_peserta === null) {
            return true;
        }

        if ($additionalApprovals <= 0) {
            return true;
        }

        return $this->countApprovedParticipants($turnamen) + $additionalApprovals <= (int) $turnamen->maks_peserta;
    }

    public function assertCanApprove(Turnamen $turnamen, int $additionalApprovals = 1): void
    {
        if ($this->canApprove($turnamen, $additionalApprovals)) {
            return;
        }

        $approved = $this->countApprovedParticipants($turnamen);
        $limit = (int) $turnamen->maks_peserta;

        throw new RuntimeException(sprintf(
            'Kapasitas turnamen penuh. Maksimal %d peserta disetujui. Saat ini %d disetujui, tidak dapat menyetujui %d lagi.',
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
