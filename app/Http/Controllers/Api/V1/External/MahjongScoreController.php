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
use App\Models\TurnamenMeja;
use App\Services\MahjongMatchmakingService;
use App\Services\MahjongTeamMatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use RuntimeException;

class MahjongScoreController extends Controller
{
    protected $mahjongService;

    protected $mahjongTeamService;

    public function __construct(
        MahjongMatchmakingService $mahjongService,
        MahjongTeamMatchmakingService $mahjongTeamService
    ) {
        $this->mahjongService = $mahjongService;
        $this->mahjongTeamService = $mahjongTeamService;
    }

    public function groups(int $id): JsonResponse
    {
        $turnamen = $this->findMahjongTurnamen($id);

        if ($turnamen instanceof JsonResponse) {
            return $turnamen;
        }

        $groups = $turnamen->isMahjongTeam()
            ? $this->mahjongTeamGroupPayloads($turnamen)
            : Grup::query()
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

        if ($turnamen->isMahjongTeam()) {
            return $this->storeMahjongTeamMejaScores($request, $turnamen);
        }

        $grup = Grup::with('members')->find($request->input('id_grup'));

        if (! $grup || (int) $grup->id_turnamen !== (int) $turnamen->id) {
            return response()->json([
                'success' => false,
                'message' => 'Grup tidak ditemukan pada turnamen ini.',
            ], 404);
        }

        if ($blocked = $this->rejectIfExternalScoringDisabled(
            $turnamen,
            $request->input('id_kategori') ?? $grup->id_kategori
        )) {
            return $blocked;
        }

        try {
            $scores = $this->normalizeScores($grup->members, $request->input('scores', []));
            $winnerMemberId = null;

            if ($request->filled('id_grup_member_pemenang')) {
                $winnerMemberId = $this->resolveWinnerMemberId(
                    $grup->members,
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
                'turnamen' => $this->turnamenPayload($turnamen->fresh()),
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

        if ($blocked = $this->rejectIfExternalScoringDisabled(
            $turnamen,
            $request->input('id_kategori') ?? $member->grup->id_kategori
        )) {
            return $blocked;
        }

        try {
            $updated = $turnamen->isMahjongTeam()
                ? $this->mahjongTeamService->addMemberPointEntry(
                    $member,
                    (int) $request->input('poin')
                )
                : $this->mahjongService->addMemberPointEntry(
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

        if ($blocked = $this->rejectIfExternalScoringDisabled(
            $turnamen,
            $request->input('id_kategori') ?? $member->grup->id_kategori
        )) {
            return $blocked;
        }

        try {
            $updated = $turnamen->isMahjongTeam()
                ? $this->mahjongTeamService->updateMemberPointEntry(
                    $member,
                    $entry,
                    (int) $request->input('poin')
                )
                : $this->mahjongService->updateMemberPointEntry(
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

        if (! $turnamen->isMahjongFormat()) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint ini hanya tersedia untuk turnamen Mahjong atau Mahjong Tim.',
            ], 422);
        }

        return $turnamen;
    }

    /**
     * @param  int|string|null  $idKategori
     */
    protected function rejectIfExternalScoringDisabled(Turnamen $turnamen, $idKategori = null): ?JsonResponse
    {
        if ($this->mahjongService->isExternalScoringEnabled($turnamen, $idKategori)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Input skor eksternal sedang dinonaktifkan untuk turnamen ini.',
            'data' => [
                'mahjong_external_scoring_enabled' => false,
            ],
        ], 403);
    }

    protected function storeMahjongTeamMejaScores(StoreMahjongGroupScoresRequest $request, Turnamen $turnamen): JsonResponse
    {
        $mejaId = (int) ($request->input('id_meja') ?: $request->input('id_grup'));
        $meja = TurnamenMeja::with('seats.grupMember')->find($mejaId);

        if (! $meja || (int) $meja->id_turnamen !== (int) $turnamen->id) {
            return response()->json([
                'success' => false,
                'message' => 'Meja tidak ditemukan pada turnamen ini.',
            ], 404);
        }

        if ($blocked = $this->rejectIfExternalScoringDisabled(
            $turnamen,
            $request->input('id_kategori') ?? $meja->id_kategori
        )) {
            return $blocked;
        }

        $members = $meja->seats
            ->map(function ($seat) {
                return $seat->grupMember;
            })
            ->filter()
            ->values();

        try {
            $scores = $this->normalizeScores($members, $request->input('scores', []));
            $winnerMemberId = null;

            if ($request->filled('id_grup_member_pemenang')) {
                $winnerMemberId = $this->resolveWinnerMemberId(
                    $members,
                    $scores,
                    (int) $request->input('id_grup_member_pemenang')
                );
            }

            $this->mahjongTeamService->addMejaPointEntries($meja, $scores, $winnerMemberId);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $payload = $this->mejaAsGroupPayload(
            $meja->fresh([
                'seats.grupMember.pemain',
                'seats.grupMember.poinEntries',
                'seats.grupMember.turnamenPeserta.pemain1',
                'seats.grupMember.grup',
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Poin meja berhasil disimpan.',
            'data' => [
                'turnamen' => $this->turnamenPayload($turnamen->fresh()),
                'grup' => $payload,
                'meja' => $payload,
                'members' => $payload['members'],
            ],
        ], 201);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function mahjongTeamGroupPayloads(Turnamen $turnamen): Collection
    {
        return TurnamenMeja::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('is_aktif', true)
            ->with([
                'seats.grupMember.pemain',
                'seats.grupMember.poinEntries',
                'seats.grupMember.turnamenPeserta.pemain1',
                'seats.grupMember.grup',
            ])
            ->orderBy('nama')
            ->orderBy('id')
            ->get()
            ->map(function (TurnamenMeja $meja) {
                return $this->mejaAsGroupPayload($meja);
            })
            ->values();
    }

    /**
     * @param  iterable  $members
     * @param  array<int, array<string, mixed>>  $scores
     * @return array<int, array{id:int, poin:int}>
     */
    protected function normalizeScores($members, array $scores): array
    {
        $memberList = collect($members)->filter();
        $normalized = [];

        foreach ($scores as $index => $score) {
            $member = $this->resolveScoreMember($memberList, $score);

            if (! $member) {
                throw new RuntimeException('Pemain pada scores['.$index.'] tidak ditemukan. Isi id_grup_member atau id_pemain.');
            }

            $normalized[] = [
                'id' => (int) $member->id,
                'poin' => (int) $score['poin'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  iterable  $members
     * @param  array<int, array{id:int, poin:int}>  $scores
     */
    protected function resolveWinnerMemberId($members, array $scores, int $winnerMemberId): int
    {
        $scoreIds = collect($scores)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! in_array($winnerMemberId, $scoreIds, true)) {
            throw new RuntimeException('id_grup_member_pemenang harus salah satu pemain di scores.');
        }

        if (! collect($members)->firstWhere('id', $winnerMemberId)) {
            throw new RuntimeException('Pemenang harus salah satu pemain di meja atau grup.');
        }

        return $winnerMemberId;
    }

    /**
     * @param  iterable  $members
     * @param  array<string, mixed>  $score
     */
    protected function resolveScoreMember($members, array $score): ?GrupMember
    {
        $memberList = collect($members);
        $memberId = $score['id_grup_member'] ?? $score['id'] ?? null;

        if ($memberId) {
            return $memberList->firstWhere('id', (int) $memberId);
        }

        if (! empty($score['id_pemain'])) {
            return $memberList->firstWhere('id_pemain', (int) $score['id_pemain']);
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
            'mahjong_external_scoring_enabled' => $this->mahjongService->isExternalScoringEnabled($turnamen),
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

    /**
     * Shape a Mahjong Tim table as a "group" so Omahjong's Grup tab can reuse the same payload.
     *
     * @return array<string, mixed>
     */
    protected function mejaAsGroupPayload(TurnamenMeja $meja): array
    {
        $meja->loadMissing([
            'seats.grupMember.pemain',
            'seats.grupMember.poinEntries',
            'seats.grupMember.turnamenPeserta.pemain1',
            'seats.grupMember.grup',
        ]);

        return [
            'id' => $meja->id,
            'id_meja' => $meja->id,
            'nama' => $meja->nama,
            'babak' => (int) $meja->babak,
            'ronde' => (int) $meja->ronde,
            'is_aktif' => (bool) $meja->is_aktif,
            'members' => $meja->seats->map(function ($seat) {
                $member = $seat->grupMember;

                if (! $member) {
                    return null;
                }

                $payload = $this->memberPayload($member);
                $payload['id_tim'] = $member->id_grup;
                $payload['tim'] = optional($member->grup)->nama;
                $payload['seat_order'] = (int) $seat->seat_order;

                return $payload;
            })->filter()->values(),
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
            'poin_penyesuaian' => (int) $member->poin_penyesuaian,
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
