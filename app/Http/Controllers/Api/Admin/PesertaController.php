<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkApprovePesertaRequest;
use App\Http\Requests\Admin\SetPesertaPartnerRequest;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use App\Services\DoublePartnerManagementService;
use App\Services\PesertaApprovalService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PesertaController extends Controller
{
    protected $approvalService;
    protected $partnerService;

    public function __construct(
        PesertaApprovalService $approvalService,
        DoublePartnerManagementService $partnerService
    ) {
        $this->approvalService = $approvalService;
        $this->partnerService = $partnerService;
    }

    public function bulkApprove(BulkApprovePesertaRequest $request): JsonResponse
    {
        $turnamen = Turnamen::findOrFail($request->id_turnamen);

        try {
            $result = $this->approvalService->bulkApprove($turnamen, $request->peserta_ids);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'maks_peserta' => $turnamen->maks_peserta,
                ],
            ], 422);
        }

        $approvedCount = $result['approved']->count();
        $skippedCount = $result['already_approved']->count();

        return response()->json([
            'success' => true,
            'message' => $approvedCount > 0
                ? sprintf('%d peserta berhasil disetujui.', $approvedCount)
                : 'Semua peserta yang dipilih sudah disetujui sebelumnya.',
            'data' => [
                'approved_count' => $approvedCount,
                'skipped_already_approved' => $skippedCount,
                'approved' => $result['approved']->values(),
                'already_approved' => $result['already_approved']->values(),
                'maks_peserta' => $turnamen->maks_peserta,
            ],
        ]);
    }

    public function setPartner(SetPesertaPartnerRequest $request, TurnamenPeserta $peserta): JsonResponse
    {
        try {
            if ($request->filled('partner_peserta_id')) {
                $partnerPeserta = TurnamenPeserta::findOrFail($request->partner_peserta_id);
                $pasangan = $this->partnerService->pairWithPeserta($peserta, $partnerPeserta);
            } else {
                $pasangan = $this->partnerService->pairWithNewPlayer(
                    $peserta,
                    $request->only(['nama', 'no_hp', 'gender', 'tgl_lahir', 'rating']),
                    $request->file('foto')
                );
            }
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $pasangan->load(['peserta1.pemain1', 'peserta2.pemain1']);

        return response()->json([
            'success' => true,
            'message' => 'Pasangan berhasil diperbarui.',
            'data' => [
                'pasangan_id' => $pasangan->id,
                'peserta_id' => $peserta->id,
                'display_name' => $pasangan->display_name,
                'peserta1' => $pasangan->peserta1,
                'peserta2' => $pasangan->peserta2,
            ],
        ]);
    }

    public function removePartner(TurnamenPeserta $peserta): JsonResponse
    {
        try {
            $peserta = $this->partnerService->removePartner($peserta);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pasangan berhasil dilepas.',
            'data' => $peserta,
        ]);
    }
}
