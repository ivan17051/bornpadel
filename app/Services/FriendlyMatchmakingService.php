<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FriendlyMatchmakingService
{
    public const PLAYERS_PER_GROUP = 4;

    public function canGenerateGroups(Turnamen $turnamen): bool
    {
        return $turnamen->isFriendly()
            && $turnamen->status === 'ongoing'
            && ! $turnamen->grup()->exists();
    }

    public function canCreateSkeletonGroups(Turnamen $turnamen): bool
    {
        return $this->canGenerateGroups($turnamen)
            && $this->previewGroupSplit($this->getApprovedEntries($turnamen)->count()) !== null;
    }

    public function canRandomizeUnassigned(Turnamen $turnamen): bool
    {
        return $this->canEditGroups($turnamen)
            && $this->getUnassignedApprovedEntries($turnamen)->isNotEmpty();
    }

    public function canRenameGroup(Turnamen $turnamen): bool
    {
        return $this->canEditGroups($turnamen);
    }

    public function canAssignMember(Turnamen $turnamen): bool
    {
        return $this->canEditGroups($turnamen);
    }

    public function canEditGroups(Turnamen $turnamen): bool
    {
        return $turnamen->isFriendly()
            && $turnamen->status === 'ongoing'
            && $turnamen->grup()->exists()
            && ! $this->hasAssignedFriendlyMatches($turnamen);
    }

    public function canAddMatch(Turnamen $turnamen): bool
    {
        return $turnamen->isFriendly()
            && $turnamen->status === 'ongoing'
            && $turnamen->grup()->count() >= 2;
    }

    public function canAssignPairs(Pertandingan $match): bool
    {
        if ($match->nama_ronde !== 'Friendly' || $match->status === 'completed') {
            return false;
        }

        if ($match->skor()->exists()) {
            return false;
        }

        $turnamen = $match->relationLoaded('turnamen')
            ? $match->turnamen
            : Turnamen::find($match->id_turnamen);

        return $turnamen
            && $turnamen->isFriendly()
            && $turnamen->status === 'ongoing'
            && $match->id_grup1
            && $match->id_grup2;
    }

    public function canReset(Turnamen $turnamen): bool
    {
        if (! $turnamen->isFriendly() || $turnamen->status !== 'ongoing') {
            return false;
        }

        if (! $turnamen->grup()->exists() && ! $turnamen->pertandingan()->exists()) {
            return false;
        }

        return ! $this->hasCompletedMatches($turnamen);
    }

    public function hasCompletedMatches(Turnamen $turnamen): bool
    {
        return $turnamen->pertandingan()
            ->where('nama_ronde', 'Friendly')
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * True once any Friendly match has pairs filled (Isi Pasangan / Tambah Tanding).
     * Empty auto-created slots do not lock group member swaps.
     */
    public function hasAssignedFriendlyMatches(Turnamen $turnamen): bool
    {
        return $turnamen->pertandingan()
            ->where('nama_ronde', 'Friendly')
            ->where(function ($query) {
                $query->whereNotNull('id_pemain1')
                    ->orWhereNotNull('id_pemain2')
                    ->orWhere('status', 'completed');
            })
            ->exists();
    }

    public function getApprovedEntries(Turnamen $turnamen): Collection
    {
        return TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->with('pemain1')
            ->orderBy('id')
            ->get();
    }

    public function previewGroupSplit(int $approvedCount): ?array
    {
        if ($approvedCount < self::PLAYERS_PER_GROUP || $approvedCount % self::PLAYERS_PER_GROUP !== 0) {
            return null;
        }

        $groupCount = intdiv($approvedCount, self::PLAYERS_PER_GROUP);

        if ($groupCount < 2) {
            return null;
        }

        $sizes = array_fill(0, $groupCount, self::PLAYERS_PER_GROUP);

        return [
            'group_count' => $groupCount,
            'sizes' => $sizes,
            'label' => implode(' + ', $sizes),
            'match_slots' => (int) (($groupCount * ($groupCount - 1)) / 2),
        ];
    }

    /**
     * @return array{groups: Collection, mode: string, group_count: int, match_slots: int}
     */
    public function generateGroups(Turnamen $turnamen, string $mode = 'random'): array
    {
        if (! $this->canGenerateGroups($turnamen)) {
            throw new RuntimeException('Grup Group Match belum dapat dibuat. Pastikan turnamen ongoing dan belum punya grup.');
        }

        $entries = $this->getApprovedEntries($turnamen);
        $preview = $this->previewGroupSplit($entries->count());

        if (! $preview) {
            throw new RuntimeException(
                'Group Match membutuhkan minimal 8 pemain approved dan kelipatan 4 (grup berisi 4 pemain).'
            );
        }

        $ordered = $this->orderEntries($entries, $mode);

        return DB::transaction(function () use ($turnamen, $ordered, $mode, $preview) {
            $chunks = $ordered->chunk(self::PLAYERS_PER_GROUP)->values();
            $groups = collect();

            foreach ($chunks as $index => $chunk) {
                $grup = Grup::create([
                    'id_turnamen' => $turnamen->id,
                    'nama' => 'Grup ' . chr(65 + $index),
                    'babak' => 1,
                    'ronde' => 1,
                    'is_aktif' => true,
                    'poin_didapat' => 0,
                    'set_menang' => 0,
                    'games_menang' => 0,
                ]);

                foreach ($chunk as $peserta) {
                    GrupMember::create([
                        'id_grup' => $grup->id,
                        'id_pemain' => $peserta->id_pemain1,
                        'id_turnamen_peserta' => $peserta->id,
                        'poin_didapat' => 0,
                        'set_menang' => 0,
                        'games_menang' => 0,
                        'poin_akumulasi' => 0,
                    ]);
                }

                $groups->push($grup->load(['members.pemain']));
            }

            $slots = $this->createInterGroupSlots($turnamen, $groups);

            return [
                'groups' => $groups,
                'mode' => $mode,
                'group_count' => $preview['group_count'],
                'match_slots' => $slots->count(),
            ];
        });
    }

    /**
     * Create one empty Friendly match slot for every unique group pair.
     *
     * @param  Collection<int, Grup>  $groups
     * @return Collection<int, Pertandingan>
     */
    public function createInterGroupSlots(Turnamen $turnamen, Collection $groups): Collection
    {
        $list = $groups->values();
        $slots = collect();

        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                $slots->push(Pertandingan::create([
                    'id_turnamen' => $turnamen->id,
                    'id_grup' => null,
                    'id_grup1' => $list[$i]->id,
                    'id_grup2' => $list[$j]->id,
                    'nama_ronde' => 'Friendly',
                    'id_pemain1' => null,
                    'id_pemain2' => null,
                    'id_pemain1_partner' => null,
                    'id_pemain2_partner' => null,
                    'id_peserta1' => null,
                    'id_peserta2' => null,
                    'status' => 'scheduled',
                ]));
            }
        }

        return $slots;
    }

    public function getUnassignedApprovedEntries(Turnamen $turnamen): Collection
    {
        $assignedPesertaIds = GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id);
            })
            ->whereNotNull('id_turnamen_peserta')
            ->pluck('id_turnamen_peserta')
            ->all();

        return $this->getApprovedEntries($turnamen)
            ->reject(fn (TurnamenPeserta $peserta) => in_array($peserta->id, $assignedPesertaIds, true))
            ->values();
    }

    /**
     * Create empty groups (Grup A, B, ...) without assigning players.
     *
     * @return array{groups: Collection, group_count: int}
     */
    public function createSkeletonGroups(Turnamen $turnamen): array
    {
        if (! $this->canCreateSkeletonGroups($turnamen)) {
            throw new RuntimeException('Kerangka grup Group Match belum dapat dibuat.');
        }

        $entries = $this->getApprovedEntries($turnamen);
        $preview = $this->previewGroupSplit($entries->count());

        if (! $preview) {
            throw new RuntimeException(
                'Group Match membutuhkan minimal 8 pemain approved dan kelipatan 4 (grup berisi 4 pemain).'
            );
        }

        return DB::transaction(function () use ($turnamen, $preview) {
            $groups = collect();

            for ($index = 0; $index < $preview['group_count']; $index++) {
                $groups->push($this->createEmptyGroup($turnamen, $index));
            }

            return [
                'groups' => $groups,
                'group_count' => $preview['group_count'],
            ];
        });
    }

    /**
     * Fill only unassigned players into groups that still have open seats.
     *
     * @return array{assigned_count: int, match_slots: int, mode: string}
     */
    public function randomizeUnassigned(Turnamen $turnamen, string $mode = 'random'): array
    {
        if (! $this->canRandomizeUnassigned($turnamen)) {
            throw new RuntimeException('Tidak ada pemain yang perlu diacak ke grup.');
        }

        $unassigned = $this->orderEntries($this->getUnassignedApprovedEntries($turnamen), $mode);

        return DB::transaction(function () use ($turnamen, $unassigned, $mode) {
            $groups = $turnamen->grup()->withCount('members')->orderBy('id')->get();
            $remainingSeats = $groups->sum(fn (Grup $grup) => max(0, self::PLAYERS_PER_GROUP - (int) $grup->members_count));

            if ($unassigned->count() > $remainingSeats) {
                throw new RuntimeException(
                    'Kursi grup tidak cukup untuk semua pemain belum digrup. Tambah kerangka grup atau kurangi pemain.'
                );
            }

            $queue = $unassigned->values();
            $assignedCount = 0;

            foreach ($groups as $grup) {
                $currentCount = (int) $grup->members()->count();
                $open = self::PLAYERS_PER_GROUP - $currentCount;

                for ($i = 0; $i < $open && $queue->isNotEmpty(); $i++) {
                    $peserta = $queue->shift();
                    $this->createMember($grup, $peserta);
                    $assignedCount++;
                }
            }

            if ($queue->isNotEmpty()) {
                throw new RuntimeException('Masih ada pemain yang tidak mendapat kursi grup.');
            }

            $slots = $this->ensureInterGroupSlots($turnamen);

            return [
                'assigned_count' => $assignedCount,
                'match_slots' => $slots->count(),
                'mode' => $mode,
            ];
        });
    }

    public function assignMemberToGroup(Turnamen $turnamen, int $grupId, int $pesertaId): GrupMember
    {
        $members = $this->assignMembersToGroup($turnamen, $grupId, [$pesertaId]);

        return $members[0];
    }

    /**
     * @param  array<int, int>  $pesertaIds
     * @return array<int, GrupMember>
     */
    public function assignMembersToGroup(Turnamen $turnamen, int $grupId, array $pesertaIds): array
    {
        if (! $this->canAssignMember($turnamen)) {
            throw new RuntimeException('Anggota grup tidak dapat diubah saat ini.');
        }

        $pesertaIds = array_values(array_unique(array_map('intval', $pesertaIds)));

        if ($pesertaIds === []) {
            throw new RuntimeException('Pilih minimal satu pemain.');
        }

        $grup = Grup::where('id_turnamen', $turnamen->id)->where('id', $grupId)->first();

        if (! $grup) {
            throw new RuntimeException('Grup tidak ditemukan pada turnamen ini.');
        }

        $currentCount = $grup->members()->count();
        $slotsRemaining = self::PLAYERS_PER_GROUP - $currentCount;

        if ($slotsRemaining <= 0) {
            throw new RuntimeException("{$grup->nama} sudah penuh (maksimal 4 pemain).");
        }

        if (count($pesertaIds) > $slotsRemaining) {
            throw new RuntimeException("{$grup->nama} hanya punya sisa {$slotsRemaining} slot.");
        }

        $pesertaList = TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->approved()
            ->whereIn('id', $pesertaIds)
            ->get()
            ->keyBy('id');

        if ($pesertaList->count() !== count($pesertaIds)) {
            throw new RuntimeException('Ada peserta yang tidak ditemukan atau belum approved.');
        }

        foreach ($pesertaIds as $pesertaId) {
            if ($this->isPesertaAssigned($turnamen, $pesertaId)) {
                $nama = optional($pesertaList->get($pesertaId)->pemain1)->nama ?? 'Pemain';
                throw new RuntimeException("{$nama} sudah berada di salah satu grup.");
            }
        }

        return DB::transaction(function () use ($turnamen, $grup, $pesertaIds, $pesertaList) {
            $created = [];

            foreach ($pesertaIds as $pesertaId) {
                $created[] = $this->createMember($grup, $pesertaList->get($pesertaId))
                    ->load(['pemain', 'turnamenPeserta']);
            }

            $this->ensureInterGroupSlots($turnamen);

            return $created;
        });
    }

    public function unassignMember(Turnamen $turnamen, GrupMember $member): void
    {
        if (! $this->canAssignMember($turnamen)) {
            throw new RuntimeException('Anggota grup tidak dapat diubah saat ini.');
        }

        $member->loadMissing('grup');

        if (! $member->grup || (int) $member->grup->id_turnamen !== (int) $turnamen->id) {
            throw new RuntimeException('Anggota tidak termasuk turnamen ini.');
        }

        if ($this->hasAssignedFriendlyMatches($turnamen)) {
            throw new RuntimeException('Anggota tidak dapat dilepas setelah pasangan pertandingan diisi.');
        }

        DB::transaction(function () use ($turnamen, $member) {
            $member->delete();

            // Drop empty fixtures so they can be regenerated after groups are complete again.
            Pertandingan::where('id_turnamen', $turnamen->id)
                ->where('nama_ronde', 'Friendly')
                ->whereNull('id_pemain1')
                ->delete();
        });
    }

    public function renameGroup(Turnamen $turnamen, Grup $grup, string $nama): Grup
    {
        if (! $this->canRenameGroup($turnamen)) {
            throw new RuntimeException('Nama grup tidak dapat diubah saat ini.');
        }

        if ((int) $grup->id_turnamen !== (int) $turnamen->id) {
            throw new RuntimeException('Grup tidak termasuk turnamen ini.');
        }

        $nama = trim($nama);

        if ($nama === '') {
            throw new RuntimeException('Nama grup wajib diisi.');
        }

        if (mb_strlen($nama) > 255) {
            throw new RuntimeException('Nama grup maksimal 255 karakter.');
        }

        $duplicate = Grup::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('nama', $nama)
            ->where('id', '!=', $grup->id)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Nama grup sudah digunakan pada turnamen ini.');
        }

        $grup->update(['nama' => $nama]);

        return $grup->fresh();
    }

    public function areGroupsComplete(Turnamen $turnamen): bool
    {
        $entries = $this->getApprovedEntries($turnamen);
        $preview = $this->previewGroupSplit($entries->count());

        if (! $preview) {
            return false;
        }

        $groups = $turnamen->grup()->withCount('members')->get();

        if ($groups->count() !== $preview['group_count']) {
            return false;
        }

        if ($this->getUnassignedApprovedEntries($turnamen)->isNotEmpty()) {
            return false;
        }

        return $groups->every(fn (Grup $grup) => (int) $grup->members_count === self::PLAYERS_PER_GROUP);
    }

    public function hasFriendlyMatchSlots(Turnamen $turnamen): bool
    {
        return $turnamen->pertandingan()
            ->where('nama_ronde', 'Friendly')
            ->exists();
    }

    /**
     * @return Collection<int, Pertandingan>
     */
    public function ensureInterGroupSlots(Turnamen $turnamen): Collection
    {
        if ($this->hasFriendlyMatchSlots($turnamen)) {
            return $this->getMatches($turnamen);
        }

        if (! $this->areGroupsComplete($turnamen)) {
            return collect();
        }

        $groups = $turnamen->grup()->orderBy('id')->get();

        return $this->createInterGroupSlots($turnamen, $groups);
    }

    protected function createEmptyGroup(Turnamen $turnamen, int $index): Grup
    {
        return Grup::create([
            'id_turnamen' => $turnamen->id,
            'nama' => 'Grup ' . chr(65 + $index),
            'babak' => 1,
            'ronde' => 1,
            'is_aktif' => true,
            'poin_didapat' => 0,
            'set_menang' => 0,
            'games_menang' => 0,
        ]);
    }

    protected function createMember(Grup $grup, TurnamenPeserta $peserta): GrupMember
    {
        return GrupMember::create([
            'id_grup' => $grup->id,
            'id_pemain' => $peserta->id_pemain1,
            'id_turnamen_peserta' => $peserta->id,
            'poin_didapat' => 0,
            'set_menang' => 0,
            'games_menang' => 0,
            'poin_akumulasi' => 0,
        ]);
    }

    protected function isPesertaAssigned(Turnamen $turnamen, int $pesertaId): bool
    {
        return GrupMember::query()
            ->where('id_turnamen_peserta', $pesertaId)
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id);
            })
            ->exists();
    }

    /**
     * @param  int[]  $side1PemainIds
     * @param  int[]  $side2PemainIds
     */
    public function createMatch(
        Turnamen $turnamen,
        int $grup1Id,
        int $grup2Id,
        array $side1PemainIds,
        array $side2PemainIds
    ): Pertandingan {
        if (! $this->canAddMatch($turnamen)) {
            throw new RuntimeException('Pertandingan Friendly belum dapat ditambahkan.');
        }

        if ($grup1Id === $grup2Id) {
            throw new RuntimeException('Pilih dua grup yang berbeda.');
        }

        $grup1 = Grup::where('id_turnamen', $turnamen->id)->where('id', $grup1Id)->first();
        $grup2 = Grup::where('id_turnamen', $turnamen->id)->where('id', $grup2Id)->first();

        if (! $grup1 || ! $grup2) {
            throw new RuntimeException('Grup tidak ditemukan pada turnamen ini.');
        }

        $payload = $this->buildPairPayload($turnamen, $grup1, $grup2, $side1PemainIds, $side2PemainIds);

        return Pertandingan::create(array_merge([
            'id_turnamen' => $turnamen->id,
            'id_grup' => null,
            'id_grup1' => $grup1->id,
            'id_grup2' => $grup2->id,
            'nama_ronde' => 'Friendly',
            'status' => 'scheduled',
        ], $payload));
    }

    /**
     * Fill or replace pairs on an existing Friendly match slot (before scoring).
     *
     * @param  int[]  $side1PemainIds
     * @param  int[]  $side2PemainIds
     */
    public function assignPairs(
        Pertandingan $match,
        array $side1PemainIds,
        array $side2PemainIds
    ): Pertandingan {
        if (! $this->canAssignPairs($match)) {
            throw new RuntimeException('Pasangan pada pertandingan ini tidak dapat diubah.');
        }

        $grup1 = Grup::where('id_turnamen', $match->id_turnamen)->where('id', $match->id_grup1)->first();
        $grup2 = Grup::where('id_turnamen', $match->id_turnamen)->where('id', $match->id_grup2)->first();

        if (! $grup1 || ! $grup2) {
            throw new RuntimeException('Grup pertandingan tidak ditemukan.');
        }

        $turnamen = Turnamen::findOrFail($match->id_turnamen);
        $payload = $this->buildPairPayload($turnamen, $grup1, $grup2, $side1PemainIds, $side2PemainIds);
        $match->update($payload);

        return $match->fresh([
            'pemain1',
            'pemain2',
            'pemain1Partner',
            'pemain2Partner',
            'grup1',
            'grup2',
        ]);
    }

    public function deleteScheduledMatch(Pertandingan $match): void
    {
        if ($match->nama_ronde !== 'Friendly') {
            throw new RuntimeException('Hanya pertandingan Friendly yang dapat dihapus lewat fitur ini.');
        }

        if ($match->status === 'completed' || $match->skor()->exists()) {
            throw new RuntimeException('Pertandingan yang sudah memiliki skor tidak dapat dihapus.');
        }

        $match->delete();
    }

    public function resetGroupsAndMatches(Turnamen $turnamen): void
    {
        if (! $this->canReset($turnamen)) {
            throw new RuntimeException('Grup Friendly tidak dapat di-reset karena sudah ada pertandingan selesai.');
        }

        DB::transaction(function () use ($turnamen) {
            $grupIds = $turnamen->grup()->pluck('id');

            Pertandingan::where('id_turnamen', $turnamen->id)
                ->where('nama_ronde', 'Friendly')
                ->delete();

            if ($grupIds->isNotEmpty()) {
                GrupMember::whereIn('id_grup', $grupIds)->delete();
                Grup::whereIn('id', $grupIds)->delete();
            }
        });
    }

    public function getMatches(Turnamen $turnamen): Collection
    {
        return Pertandingan::query()
            ->where('id_turnamen', $turnamen->id)
            ->where('nama_ronde', 'Friendly')
            ->with([
                'pemain1',
                'pemain2',
                'pemain1Partner',
                'pemain2Partner',
                'grup1',
                'grup2',
                'skor',
                'pemenang',
            ])
            ->orderBy('id_grup1')
            ->orderBy('id_grup2')
            ->orderBy('id')
            ->get();
    }

    protected function orderEntries(Collection $entries, string $mode): Collection
    {
        if ($mode === 'by_rating') {
            return $entries->sortByDesc(function (TurnamenPeserta $peserta) {
                return (float) optional($peserta->pemain1)->rating;
            })->values();
        }

        return $entries->shuffle()->values();
    }

    /**
     * @param  int[]  $side1PemainIds
     * @param  int[]  $side2PemainIds
     * @return array{
     *     id_pemain1: int,
     *     id_pemain2: int,
     *     id_pemain1_partner: int,
     *     id_pemain2_partner: int,
     *     id_peserta1: int|null,
     *     id_peserta2: int|null
     * }
     */
    protected function buildPairPayload(
        Turnamen $turnamen,
        Grup $grup1,
        Grup $grup2,
        array $side1PemainIds,
        array $side2PemainIds
    ): array {
        $side1 = $this->normalizePairIds($side1PemainIds);
        $side2 = $this->normalizePairIds($side2PemainIds);

        $this->assertPlayersBelongToGroup($grup1, $side1);
        $this->assertPlayersBelongToGroup($grup2, $side2);

        if (count(array_intersect($side1, $side2)) > 0) {
            throw new RuntimeException('Pemain tidak boleh bermain di kedua sisi pada pertandingan yang sama.');
        }

        $peserta1 = TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->where('id_pemain1', $side1[0])
            ->first();
        $peserta2 = TurnamenPeserta::query()
            ->forTurnamen($turnamen->id)
            ->where('id_pemain1', $side2[0])
            ->first();

        return [
            'id_pemain1' => $side1[0],
            'id_pemain2' => $side2[0],
            'id_pemain1_partner' => $side1[1],
            'id_pemain2_partner' => $side2[1],
            'id_peserta1' => optional($peserta1)->id,
            'id_peserta2' => optional($peserta2)->id,
        ];
    }

    /**
     * @param  int[]  $ids
     * @return int[]
     */
    protected function normalizePairIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_map('intval', $ids)));

        if (count($normalized) !== 2) {
            throw new RuntimeException('Setiap sisi harus terdiri dari tepat 2 pemain dari grup yang sama.');
        }

        return $normalized;
    }

    /**
     * @param  int[]  $pemainIds
     */
    protected function assertPlayersBelongToGroup(Grup $grup, array $pemainIds): void
    {
        $memberIds = $grup->members()->pluck('id_pemain')->map(fn ($id) => (int) $id)->all();

        foreach ($pemainIds as $pemainId) {
            if (! in_array($pemainId, $memberIds, true)) {
                throw new RuntimeException("Pemain #{$pemainId} tidak termasuk di {$grup->nama}.");
            }
        }
    }
}
