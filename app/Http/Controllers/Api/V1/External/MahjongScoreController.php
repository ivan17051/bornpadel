<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\External\StoreMahjongGroupScoresRequest;
use App\Http\Requests\Api\External\StoreMahjongMemberScoreRequest;
use App\Http\Requests\Api\External\UpdateMahjongScoreRequest;
use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\MahjongPoinEntry;
use App\Models\Turnamen;
use App\Services\MahjongMatchmakingService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class MahjongScoreController extends Controller
{
    protected $mahjongService;

    public function __construct(MahjongMatchmakingService $mahjongService)
    {
        $this->mahjongService = $mahjongService;
    }

    public function groups(int $id): JsonResponse
    {
        $turnamen = $this->findMahjongTurnamen($id);

        if ($turnamen instanceof JsonResponse) {
            return $turnamen;
        }

        $groups = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with(['members.pemain', 'members.poinEntries', 'members.turnamenPeserta.pemain1'])
            ->orderBy('nama')
            ->orderBy('id')
            ->get()
            ->map(function (Grup $grup) {
                return $this->groupPayload($grup);
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'turnamen' => $this->turnamenPayload($turnamen),
                'groups' => $groups,
            ],
        ]);
    }

    public function storeGroup(StoreMahjongGroupScoresRequest $request, int $id): JsonResponse
    {
        $turnamen = $this->findMahjongTurnamen($id);

        if ($turnamen instanceof JsonResponse) {
            return $turnamen;
        }

        $grup = Grup::with('members')->find($request->input('id_grup'));

        if (! $grup || (int) $grup->id_turnamen !== (int) $turnamen->id) {
            return response()->json([
                'success' => false,
                'message' => 'Grup tidak ditemukan pada turnamen ini.',
            ], 404);
        }

        try {
            $scores = $this->normalizeGroupScores($grup, $request->input('scores', []));
            $winnerMemberId = null;

            if ($request->filled('id_grup_member_pemenang')) {
                $winnerMemberId = $this->resolveWinnerMemberId(
                    $grup,
                    $scores,
                    (int) $request->input('id_grup_member_pemenang')
                );
            }

            $updatedMembers = $this->mahjongService->addGroupPointEntries(
                $grup,
                $scores,
                $winnerMemberId
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Poin grup berhasil disimpan.',
            'data' => [
                'turnamen' => $this->turnamenPayload($turnamen),
                'grup' => $this->groupPayload($grup->fresh(['members.pemain', 'members.poinEntries', 'members.turnamenPeserta.pemain1'])),
                'members' => $updatedMembers->map(function (GrupMember $member) {
                    return $this->memberPayload($member);
                })->values(),
            ],
        ], 201);
    }

    public function storeMember(StoreMahjongMemberScoreRequest $request, int $id, GrupMember $member): JsonResponse
    {
        $turnamen = $this->findMahjongTurnamen($id);

        if ($turnamen instanceof JsonResponse) {
            return $turnamen;
        }

        $member->loadMissing('grup');

        if (! $member->grup || (int) $member->grup->id_turnamen !== (int) $turnamen->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anggota grup tidak ditemukan pada turnamen ini.',
            ], 404);
        }

        try {
            $updated = $this->mahjongService->addMemberPointEntry(
                $member,
                (int) $request->input('poin')
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Poin berhasil ditambahkan.',
            'data' => $this->memberPayload($updated),
        ], 201);
    }

    public function update(UpdateMahjongScoreRequest $request, int $id, MahjongPoinEntry $entry): JsonResponse
    {
        $turnamen = $this->findMahjongTurnamen($id);

        if ($turnamen instanceof JsonResponse) {
            return $turnamen;
        }

        $member = GrupMember::with('grup')->find($entry->id_grup_member);

        if (! $member || ! $member->grup || (int) $member->grup->id_turnamen !== (int) $turnamen->id) {
            return response()->json([
                'success' => false,
                'message' => 'Entri poin tidak ditemukan pada turnamen ini.',
            ], 404);
        }

        try {
            $updated = $this->mahjongService->updateMemberPointEntry(
                $member,
                $entry,
                (int) $request->input('poin')
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Poin berhasil diperbarui.',
            'data' => $this->memberPayload($updated),
        ]);
    }

    /**
     * @return Turnamen|JsonResponse
     */
    protected function findMahjongTurnamen(int $id)
    {
        $turnamen = Turnamen::find($id);

        if (! $turnamen) {
            return response()->json([
                'success' => false,
                'message' => 'Turnamen tidak ditemukan.',
            ], 404);
        }

        if (! $turnamen->isMahjong()) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint ini hanya tersedia untuk turnamen Mahjong.',
            ], 422);
        }

        return $turnamen;
    }

    /**
     * @param  array<int, array<string, mixed>>  $scores
     * @return array<int, array{id:int, poin:int}>
     */
    protected function normalizeGroupScores(Grup $grup, array $scores): array
    {
        $members = $grup->members;
        $normalized = [];

        foreach ($scores as $index => $score) {
            $member = $this->resolveScoreMember($members, $score);

            if (! $member) {
                throw new RuntimeException('Pemain pada scores['.$index.'] tidak ditemukan di grup ini. Isi id_grup_member atau id_pemain.');
            }

            $normalized[] = [
                'id' => (int) $member->id,
                'poin' => (int) $score['poin'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{id:int, poin:int}>  $scores
     */
    protected function resolveWinnerMemberId(Grup $grup, array $scores, int $winnerMemberId): int
    {
        $scoreIds = collect($scores)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! in_array($winnerMemberId, $scoreIds, true)) {
            throw new RuntimeException('id_grup_member_pemenang harus salah satu pemain di scores.');
        }

        if (! $grup->members->firstWhere('id', $winnerMemberId)) {
            throw new RuntimeException('Pemenang harus salah satu anggota grup.');
        }

        return $winnerMemberId;
    }

    /**
     * @param  iterable  $members
     * @param  array<string, mixed>  $score
     */
    protected function resolveScoreMember($members, array $score): ?GrupMember
    {
        $memberId = $score['id_grup_member'] ?? $score['id'] ?? null;

        if ($memberId) {
            return $members->firstWhere('id', (int) $memberId);
        }

        if (! empty($score['id_pemain'])) {
            return $members->firstWhere('id_pemain', (int) $score['id_pemain']);
        }

        return null;
    }

    protected function turnamenPayload(Turnamen $turnamen): array
    {
        return [
            'id' => $turnamen->id,
            'nama' => $turnamen->nama,
            'jenis' => $turnamen->jenis,
            'status' => $turnamen->status,
            'mahjong_is_final' => (bool) $turnamen->mahjong_is_final,
        ];
    }

    protected function groupPayload(Grup $grup): array
    {
        $grup->loadMissing(['members.pemain', 'members.poinEntries', 'members.turnamenPeserta.pemain1']);

        return [
            'id' => $grup->id,
            'nama' => $grup->nama,
            'babak' => (int) $grup->babak,
            'is_aktif' => (bool) $grup->is_aktif,
            'members' => $grup->members->map(function (GrupMember $member) {
                return $this->memberPayload($member);
            })->values(),
        ];
    }

    protected function memberPayload(GrupMember $member): array
    {
        $member->loadMissing(['pemain', 'poinEntries', 'turnamenPeserta.pemain1']);

        return [
            'id_grup_member' => $member->id,
            'id_pemain' => $member->id_pemain,
            'id_peserta' => $member->id_turnamen_peserta,
            'nama' => $member->display_name,
            'poin_didapat' => (int) $member->poin_didapat,
            'poin_akumulasi' => (int) $member->poin_akumulasi,
            'total_poin' => $member->total_poin,
            'menang' => (int) $member->menang,
            'entries' => $member->poinEntries->map(function (MahjongPoinEntry $entry) {
                return [
                    'id' => $entry->id,
                    'poin' => (int) $entry->poin,
                    'is_winner' => (bool) $entry->is_winner,
                ];
            })->values(),
        ];
    }
}
