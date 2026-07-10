<?php

namespace App\Services;

use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PesertaApprovalService
{
    protected $capacityService;

    public function __construct(TournamentCapacityService $capacityService)
    {
        $this->capacityService = $capacityService;
    }

    public function approvePeserta(TurnamenPeserta $peserta, Turnamen $turnamen): TurnamenPeserta
    {
        if ($peserta->status !== 'approved') {
            $this->capacityService->assertCanApprove($turnamen, 1);
            $peserta->update(['status' => 'approved']);
        }

        return $peserta->fresh();
    }

    /**
     * @param  array<int>  $pesertaIds
     * @return array{approved: Collection, already_approved: Collection}
     */
    public function bulkApprove(Turnamen $turnamen, array $pesertaIds): array
    {
        $pesertaIds = array_values(array_unique(array_map('intval', $pesertaIds)));

        if ($pesertaIds === []) {
            throw new RuntimeException('Daftar peserta wajib diisi.');
        }

        $pesertaRows = TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->whereIn('id', $pesertaIds)
            ->get();

        if ($pesertaRows->count() !== count($pesertaIds)) {
            throw new RuntimeException('Satu atau lebih peserta tidak ditemukan pada turnamen ini.');
        }

        $newApprovals = $this->capacityService->countNewApprovalsNeeded($pesertaRows);
        $this->capacityService->assertCanApprove($turnamen, $newApprovals);

        return DB::transaction(function () use ($pesertaRows) {
            $approved = collect();
            $alreadyApproved = collect();

            foreach ($pesertaRows as $peserta) {
                if ($peserta->status === 'approved') {
                    $alreadyApproved->push($peserta);
                    continue;
                }

                $peserta->update(['status' => 'approved']);
                $approved->push($peserta->fresh());
            }

            return [
                'approved' => $approved,
                'already_approved' => $alreadyApproved,
            ];
        });
    }
}
