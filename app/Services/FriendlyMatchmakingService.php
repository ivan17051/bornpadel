<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Pertandingan;
use App\Models\Turnamen;
use App\Models\TurnamenGrupPendaftaran;
use App\Models\TurnamenPeserta;
use App\Services\Concerns\ResolvesTurnamenKategori;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FriendlyMatchmakingService
{
    use ResolvesTurnamenKategori;

    /** @deprecated Use Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP / friendlyPlayersPerGroup() */
    public const PLAYERS_PER_GROUP = 4;

    public function playersPerGroup(Turnamen $turnamen, $idKategori = null): int
    {
        return $turnamen->resolveKategori($idKategori)->friendlyPlayersPerGroup();
    }

    public function canGenerateGroups(Turnamen $turnamen, $idKategori = null): bool
    {
        return $turnamen->isFriendly()
            && $this->isCompetitionOngoing($turnamen, $idKategori)
            && ! $turnamen->competitionGrup($idKategori)->exists();
    }

    public function canCreateSkeletonGroups(Turnamen $turnamen, $idKategori = null): bool
    {
        return $this->canGenerateGroups($turnamen, $idKategori)
            && $this->previewGroupSplit(
                $this->getApprovedEntries($turnamen, $idKategori)->count(),
                $this->playersPerGroup($turnamen, $idKategori)
            ) !== null;
    }

    public function canRandomizeUnassigned(Turnamen $turnamen, $idKategori = null): bool
    {
        return $this->canEditGroups($turnamen, $idKategori)
            && $this->getUnassignedApprovedEntries($turnamen, $idKategori)->isNotEmpty();
    }

    public function canRenameGroup(Turnamen $turnamen, $idKategori = null): bool
    {
        return $this->canEditGroups($turnamen, $idKategori);
    }

    public function canAssignMember(Turnamen $turnamen, $idKategori = null): bool
    {
        return $this->canEditGroups($turnamen, $idKategori);
    }

    public function canEditGroups(Turnamen $turnamen, $idKategori = null): bool
    {
        return $turnamen->isFriendly()
            && $this->isCompetitionOngoing($turnamen, $idKategori)
            && $turnamen->competitionGrup($idKategori)->exists()
            && ! $this->hasAssignedFriendlyMatches($turnamen, $idKategori);
    }

    public function canAddMatch(Turnamen $turnamen, $idKategori = null): bool
    {
        return $turnamen->isFriendly()
            && $this->isCompetitionOngoing($turnamen, $idKategori)
            && $turnamen->competitionGrup($idKategori)->count() >= 2;
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

        if (! $turnamen || ! $turnamen->isFriendly()) {
            return false;
        }

        $kategoriId = $match->id_kategori ?: null;

        return $this->isCompetitionOngoing($turnamen, $kategoriId)
            && $match->id_grup1
            && $match->id_grup2;
    }

    public function canReset(Turnamen $turnamen, $idKategori = null): bool
    {
        if (! $turnamen->isFriendly() || ! $this->isCompetitionOngoing($turnamen, $idKategori)) {
            return false;
        }

        if (! $turnamen->competitionGrup($idKategori)->exists() && ! $turnamen->competitionPertandingan($idKategori)->exists()) {
            return false;
        }

        return ! $this->hasCompletedMatches($turnamen, $idKategori);
    }

    public function hasCompletedMatches(Turnamen $turnamen, $idKategori = null): bool
    {
        return $turnamen->competitionPertandingan($idKategori)
            ->where('nama_ronde', 'Friendly')
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * True once any Friendly match has pairs filled (Isi Pasangan / Tambah Tanding).
     * Empty auto-created slots do not lock group member swaps.
     */
    public function hasAssignedFriendlyMatches(Turnamen $turnamen, $idKategori = null): bool
    {
        return $turnamen->competitionPertandingan($idKategori)
            ->where('nama_ronde', 'Friendly')
            ->where(function ($query) {
                $query->whereNotNull('id_pemain1')
                    ->orWhereNotNull('id_pemain2')
                    ->orWhere('status', 'completed');
            })
            ->exists();
    }

    public function getApprovedEntries(Turnamen $turnamen, $idKategori = null): Collection
    {
        return TurnamenPeserta::query()
            ->forKategori($turnamen->resolveKategori($idKategori)->id)
            ->approved()
            ->with('pemain1')
            ->orderBy('id')
            ->get();
    }

    public function previewGroupSplit(int $approvedCount, ?int $playersPerGroup = null): ?array
    {
        $perGroup = max(
            Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP,
            (int) ($playersPerGroup ?: self::PLAYERS_PER_GROUP)
        );
        $minPlayers = $perGroup * 2;

        if ($approvedCount < $minPlayers || $approvedCount % $perGroup !== 0) {
            return null;
        }

        $groupCount = intdiv($approvedCount, $perGroup);

        if ($groupCount < 2) {
            return null;
        }

        $sizes = array_fill(0, $groupCount, $perGroup);

        return [
            'group_count' => $groupCount,
            'sizes' => $sizes,
            'label' => implode(' + ', $sizes),
            'match_slots' => (int) (($groupCount * ($groupCount - 1)) / 2),
            'players_per_group' => $perGroup,
        ];
    }

    protected function groupSplitRequirementMessage(Turnamen $turnamen, $idKategori = null): string
    {
        $perGroup = $this->playersPerGroup($turnamen, $idKategori);
        $minPlayers = $perGroup * 2;

        return sprintf(
            'Group Match membutuhkan minimal %d pemain approved dan kelipatan %d (grup berisi %d pemain).',
            $minPlayers,
            $perGroup,
            $perGroup
        );
    }

    /**
     * @return array{groups: Collection, mode: string, group_count: int, match_slots: int}
     */
    public function generateGroups(Turnamen $turnamen, string $mode = 'random', $idKategori = null): array
    {
        if (! $this->canGenerateGroups($turnamen, $idKategori)) {
            throw new RuntimeException('Grup Group Match belum dapat dibuat. Pastikan kategori ongoing dan belum punya grup.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $entries = $this->getApprovedEntries($turnamen, $kategori->id);
        $perGroup = $this->playersPerGroup($turnamen, $kategori->id);
        $preview = $this->previewGroupSplit($entries->count(), $perGroup);

        if (! $preview) {
            throw new RuntimeException($this->groupSplitRequirementMessage($turnamen, $kategori->id));
        }

        return DB::transaction(function () use ($turnamen, $kategori, $entries, $mode, $preview, $perGroup) {
            $materialized = $this->materializeCompletePreGroups($turnamen, $kategori->id);
            $groups = $materialized['groups'];
            $assignedPesertaIds = $materialized['peserta_ids'];

            $solos = $entries
                ->reject(fn (TurnamenPeserta $peserta) => in_array($peserta->id, $assignedPesertaIds, true))
                ->values();

            $orderedSolos = $this->orderEntries($solos, $mode);
            $usedNames = $groups->pluck('nama')
                ->map(fn ($nama) => mb_strtolower(trim((string) $nama)))
                ->all();
            $letterIndex = 0;

            foreach ($orderedSolos->chunk($perGroup)->values() as $chunk) {
                $grup = Grup::create([
                    'id_turnamen' => $turnamen->id,
                    'id_kategori' => $kategori->id,
                    'nama' => $this->nextAvailableLetterGroupName($usedNames, $letterIndex),
                    'babak' => 1,
                    'ronde' => 1,
                    'is_aktif' => true,
                    'poin_didapat' => 0,
                    'set_menang' => 0,
                    'games_menang' => 0,
                ]);

                foreach ($chunk as $peserta) {
                    $this->createMember($grup, $peserta);
                }

                $groups->push($grup->load(['members.pemain']));
            }

            $slots = $this->createInterGroupSlots($turnamen, $groups, $kategori->id);

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
    public function createInterGroupSlots(Turnamen $turnamen, Collection $groups, $idKategori = null): Collection
    {
        $kategoriId = $idKategori !== null && $idKategori !== ''
            ? (int) $turnamen->resolveKategori($idKategori)->id
            : (int) ($groups->first()->id_kategori ?? $turnamen->resolveKategori()->id);

        $list = $groups->sortBy([
            ['nama', 'asc'],
            ['id', 'asc'],
        ])->values();
        $orderedIds = $list->pluck('id')->map(fn ($id) => (int) $id)->all();
        $roundMap = $this->buildParallelRoundMap($orderedIds);

        $pairs = [];

        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                $id1 = (int) $list[$i]->id;
                $id2 = (int) $list[$j]->id;
                $key = min($id1, $id2) . '-' . max($id1, $id2);
                $pairs[] = [
                    'grup1' => $list[$i],
                    'grup2' => $list[$j],
                    'round' => $roundMap[$key] ?? 999,
                ];
            }
        }

        usort($pairs, function (array $a, array $b) {
            return [$a['round'], $a['grup1']->id, $a['grup2']->id]
                <=> [$b['round'], $b['grup1']->id, $b['grup2']->id];
        });

        $slots = collect();

        foreach ($pairs as $pair) {
            $slots->push(Pertandingan::create([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $kategoriId,
                'id_grup' => null,
                'id_grup1' => $pair['grup1']->id,
                'id_grup2' => $pair['grup2']->id,
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

        return $slots;
    }

    public function getUnassignedApprovedEntries(Turnamen $turnamen, $idKategori = null): Collection
    {
        $kategoriId = $turnamen->resolveKategori($idKategori)->id;

        $assignedPesertaIds = GrupMember::query()
            ->whereHas('grup', function ($query) use ($kategoriId) {
                $query->where('id_kategori', $kategoriId);
            })
            ->whereNotNull('id_turnamen_peserta')
            ->pluck('id_turnamen_peserta')
            ->all();

        return $this->getApprovedEntries($turnamen, $idKategori)
            ->reject(fn (TurnamenPeserta $peserta) => in_array($peserta->id, $assignedPesertaIds, true))
            ->values();
    }

    /**
     * Create empty groups (Grup A, B, ...) without assigning players.
     * Complete approved pre-groups are materialized as named Grup with members assigned.
     *
     * @return array{groups: Collection, group_count: int}
     */
    public function createSkeletonGroups(Turnamen $turnamen, $idKategori = null): array
    {
        if (! $this->canCreateSkeletonGroups($turnamen, $idKategori)) {
            throw new RuntimeException('Kerangka grup Group Match belum dapat dibuat.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $entries = $this->getApprovedEntries($turnamen, $kategori->id);
        $preview = $this->previewGroupSplit($entries->count(), $this->playersPerGroup($turnamen, $kategori->id));

        if (! $preview) {
            throw new RuntimeException($this->groupSplitRequirementMessage($turnamen, $kategori->id));
        }

        return DB::transaction(function () use ($turnamen, $kategori, $preview) {
            $materialized = $this->materializeCompletePreGroups($turnamen, $kategori->id);
            $groups = $materialized['groups'];
            $remainingGroupCount = $preview['group_count'] - $groups->count();

            $usedNames = $groups->pluck('nama')
                ->map(fn ($nama) => mb_strtolower(trim((string) $nama)))
                ->all();
            $letterIndex = 0;

            for ($i = 0; $i < $remainingGroupCount; $i++) {
                $groups->push($this->createEmptyGroupWithName(
                    $turnamen,
                    $this->nextAvailableLetterGroupName($usedNames, $letterIndex),
                    $kategori->id
                ));
            }

            $this->ensureInterGroupSlots($turnamen, $kategori->id);

            return [
                'groups' => $groups,
                'group_count' => $groups->count(),
            ];
        });
    }

    /**
     * Fill only unassigned players into groups that still have open seats.
     *
     * @return array{assigned_count: int, match_slots: int, mode: string}
     */
    public function randomizeUnassigned(Turnamen $turnamen, string $mode = 'random', $idKategori = null): array
    {
        if (! $this->canRandomizeUnassigned($turnamen, $idKategori)) {
            throw new RuntimeException('Tidak ada pemain yang perlu diacak ke grup.');
        }

        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $unassigned = $this->orderEntries($this->getUnassignedApprovedEntries($turnamen, $kategori->id), $mode);

        return DB::transaction(function () use ($turnamen, $kategori, $unassigned, $mode) {
            $perGroup = $this->playersPerGroup($turnamen, $kategori->id);
            $groups = $turnamen->competitionGrup($kategori->id)->withCount('members')->orderBy('id')->get();
            $remainingSeats = $groups->sum(fn (Grup $grup) => max(0, $perGroup - (int) $grup->members_count));

            if ($unassigned->count() > $remainingSeats) {
                throw new RuntimeException(
                    'Kursi grup tidak cukup untuk semua pemain belum digrup. Tambah kerangka grup atau kurangi pemain.'
                );
            }

            $queue = $unassigned->values();
            $assignedCount = 0;

            foreach ($groups as $grup) {
                $currentCount = (int) $grup->members()->count();
                $open = $perGroup - $currentCount;

                for ($i = 0; $i < $open && $queue->isNotEmpty(); $i++) {
                    $peserta = $queue->shift();
                    $this->createMember($grup, $peserta);
                    $assignedCount++;
                }
            }

            if ($queue->isNotEmpty()) {
                throw new RuntimeException('Masih ada pemain yang tidak mendapat kursi grup.');
            }

            $slots = $this->ensureInterGroupSlots($turnamen, $kategori->id);

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

        $kategoriId = (int) $grup->id_kategori;
        $currentCount = $grup->members()->count();
        $perGroup = $this->playersPerGroup($turnamen, $kategoriId);
        $slotsRemaining = $perGroup - $currentCount;

        if ($slotsRemaining <= 0) {
            throw new RuntimeException("{$grup->nama} sudah penuh (maksimal {$perGroup} pemain).");
        }

        if (count($pesertaIds) > $slotsRemaining) {
            throw new RuntimeException("{$grup->nama} hanya punya sisa {$slotsRemaining} slot.");
        }

        $pesertaList = TurnamenPeserta::query()
            ->forKategori($kategoriId)
            ->approved()
            ->whereIn('id', $pesertaIds)
            ->get()
            ->keyBy('id');

        if ($pesertaList->count() !== count($pesertaIds)) {
            throw new RuntimeException('Ada peserta yang tidak ditemukan atau belum approved.');
        }

        foreach ($pesertaIds as $pesertaId) {
            if ($this->isPesertaAssigned($turnamen, $pesertaId, $kategoriId)) {
                $nama = optional($pesertaList->get($pesertaId)->pemain1)->nama ?? 'Pemain';
                throw new RuntimeException("{$nama} sudah berada di salah satu grup.");
            }
        }

        return DB::transaction(function () use ($turnamen, $grup, $pesertaIds, $pesertaList, $kategoriId) {
            $created = [];

            foreach ($pesertaIds as $pesertaId) {
                $created[] = $this->createMember($grup, $pesertaList->get($pesertaId))
                    ->load(['pemain', 'turnamenPeserta']);
            }

            $this->ensureInterGroupSlots($turnamen, $kategoriId);

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

        $kategoriId = (int) $member->grup->id_kategori;

        if ($this->hasAssignedFriendlyMatches($turnamen, $kategoriId)) {
            throw new RuntimeException('Anggota tidak dapat dilepas setelah pasangan pertandingan diisi.');
        }

        DB::transaction(function () use ($member, $kategoriId) {
            $member->delete();

            // Drop empty fixtures so they can be regenerated after groups are complete again.
            Pertandingan::where('id_kategori', $kategoriId)
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
            ->where('id_kategori', $turnamen->resolveKategori()->id)
            ->where('nama', $nama)
            ->where('id', '!=', $grup->id)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Nama grup sudah digunakan pada kategori ini.');
        }

        $grup->update(['nama' => $nama]);

        return $grup->fresh();
    }

    public function areGroupsComplete(Turnamen $turnamen, $idKategori = null): bool
    {
        $entries = $this->getApprovedEntries($turnamen, $idKategori);
        $perGroup = $this->playersPerGroup($turnamen, $idKategori);
        $preview = $this->previewGroupSplit($entries->count(), $perGroup);

        if (! $preview) {
            return false;
        }

        $groups = $turnamen->competitionGrup($idKategori)->withCount('members')->get();

        if ($groups->count() !== $preview['group_count']) {
            return false;
        }

        if ($this->getUnassignedApprovedEntries($turnamen, $idKategori)->isNotEmpty()) {
            return false;
        }

        return $groups->every(fn (Grup $grup) => (int) $grup->members_count === $perGroup);
    }

    public function hasFriendlyMatchSlots(Turnamen $turnamen, $idKategori = null): bool
    {
        return $turnamen->competitionPertandingan($idKategori)
            ->where('nama_ronde', 'Friendly')
            ->exists();
    }

    /**
     * @return Collection<int, Pertandingan>
     */
    public function ensureInterGroupSlots(Turnamen $turnamen, $idKategori = null): Collection
    {
        if ($this->hasFriendlyMatchSlots($turnamen, $idKategori)) {
            return $this->getMatches($turnamen, $idKategori);
        }

        if (! $this->areGroupsComplete($turnamen, $idKategori)) {
            return collect();
        }

        $groups = $turnamen->competitionGrup($idKategori)->orderBy('id')->get();

        return $this->createInterGroupSlots($turnamen, $groups, $idKategori);
    }

    protected function createEmptyGroup(Turnamen $turnamen, int $index, $idKategori = null): Grup
    {
        return $this->createEmptyGroupWithName($turnamen, 'Grup ' . chr(65 + $index), $idKategori);
    }

    protected function createEmptyGroupWithName(Turnamen $turnamen, string $nama, $idKategori = null): Grup
    {
        return Grup::create([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $turnamen->resolveKategori($idKategori)->id,
            'nama' => $nama,
            'babak' => 1,
            'ronde' => 1,
            'is_aktif' => true,
            'poin_didapat' => 0,
            'set_menang' => 0,
            'games_menang' => 0,
        ]);
    }

    /**
     * Materialize complete approved registration pre-groups as real competition Grup.
     * Does not touch turnamen_grup_pendaftaran rows (resetGroups also leaves them intact).
     *
     * @return array{groups: Collection<int, Grup>, peserta_ids: array<int, int>}
     */
    public function materializeCompletePreGroups(Turnamen $turnamen, $idKategori = null): array
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);
        $preGroups = $this->getCompletePreGroups($turnamen, $kategori->id);
        $groups = collect();
        $pesertaIds = [];

        foreach ($preGroups as $preGroup) {
            $grup = Grup::create([
                'id_turnamen' => $turnamen->id,
                'id_kategori' => $kategori->id,
                'nama' => $preGroup->nama,
                'babak' => 1,
                'ronde' => 1,
                'is_aktif' => true,
                'poin_didapat' => 0,
                'set_menang' => 0,
                'games_menang' => 0,
            ]);

            foreach ($preGroup->members as $member) {
                $peserta = $member->peserta;
                if (! $peserta) {
                    continue;
                }
                $this->createMember($grup, $peserta);
                $pesertaIds[] = (int) $peserta->id;
            }

            $groups->push($grup->load(['members.pemain']));
        }

        return [
            'groups' => $groups,
            'peserta_ids' => $pesertaIds,
        ];
    }

    /**
     * @return Collection<int, TurnamenGrupPendaftaran>
     */
    public function getCompletePreGroups(Turnamen $turnamen, $idKategori = null): Collection
    {
        return TurnamenGrupPendaftaran::query()
            ->forKategori($turnamen->resolveKategori($idKategori)->id)
            ->with(['members.peserta.pemain1'])
            ->orderBy('id')
            ->get()
            ->filter(fn (TurnamenGrupPendaftaran $group) => $group->isFullyApproved($turnamen))
            ->values();
    }

    /**
     * Registration groups for admin UI (pre-groups + solo bucket).
     *
     * @return Collection<int, array{id: int|null, nama: string, is_solo_bucket: bool, is_complete: bool, members: Collection}>
     */
    public function getFriendlyRegistrationGroups(Turnamen $turnamen, $idKategori = null): Collection
    {
        $kategoriId = $turnamen->resolveKategori($idKategori)->id;

        $preGroups = TurnamenGrupPendaftaran::query()
            ->forKategori($kategoriId)
            ->with(['members.peserta.pemain1'])
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        $groupedPesertaIds = $preGroups
            ->flatMap(fn (TurnamenGrupPendaftaran $group) => $group->members->pluck('id_peserta'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $result = $preGroups->map(function (TurnamenGrupPendaftaran $group) use ($turnamen) {
            return [
                'id' => $group->id,
                'nama' => $group->nama,
                'is_solo_bucket' => false,
                'is_complete' => $group->isFullyApproved($turnamen),
                'members' => $group->members->map(function ($member) {
                    return $member->peserta;
                })->filter()->values(),
            ];
        });

        $solos = TurnamenPeserta::query()
            ->forKategori($kategoriId)
            ->whereNotIn('id', $groupedPesertaIds ?: [0])
            ->with('pemain1')
            ->orderBy('id')
            ->get();

        if ($solos->isNotEmpty() || $result->isEmpty()) {
            $result->push([
                'id' => null,
                'nama' => 'Individu / Belum berkelompok',
                'is_solo_bucket' => true,
                'is_complete' => false,
                'members' => $solos,
            ]);
        }

        return $result->values();
    }

    /**
     * @param  array<int, string>  $usedLowerNames
     */
    protected function nextAvailableLetterGroupName(array &$usedLowerNames, int &$index): string
    {
        while ($index < 100) {
            $nama = $index < 26
                ? 'Grup ' . chr(65 + $index)
                : 'Grup ' . ($index + 1);
            $index++;
            $lower = mb_strtolower($nama);

            if (! in_array($lower, $usedLowerNames, true)) {
                $usedLowerNames[] = $lower;

                return $nama;
            }
        }

        $fallback = 'Grup ' . uniqid();
        $usedLowerNames[] = mb_strtolower($fallback);

        return $fallback;
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

    protected function isPesertaAssigned(Turnamen $turnamen, int $pesertaId, $idKategori = null): bool
    {
        return GrupMember::query()
            ->where('id_turnamen_peserta', $pesertaId)
            ->whereHas('grup', function ($query) use ($turnamen, $idKategori) {
                $query->where('id_kategori', $turnamen->resolveKategori($idKategori)->id);
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
        array $side2PemainIds,
        $idKategori = null
    ): Pertandingan {
        if (! $this->canAddMatch($turnamen, $idKategori)) {
            throw new RuntimeException('Pertandingan Friendly belum dapat ditambahkan.');
        }

        if ($grup1Id === $grup2Id) {
            throw new RuntimeException('Pilih dua grup yang berbeda.');
        }

        $kategoriId = $turnamen->resolveKategori($idKategori)->id;
        $grup1 = Grup::where('id_kategori', $kategoriId)->where('id', $grup1Id)->first();
        $grup2 = Grup::where('id_kategori', $kategoriId)->where('id', $grup2Id)->first();

        if (! $grup1 || ! $grup2) {
            throw new RuntimeException('Grup tidak ditemukan pada turnamen ini.');
        }

        $payload = $this->buildPairPayload($turnamen, $grup1, $grup2, $side1PemainIds, $side2PemainIds);

        return Pertandingan::create(array_merge([
            'id_turnamen' => $turnamen->id,
            'id_kategori' => $kategoriId,
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

        $kategoriId = $match->id_kategori
            ?: Turnamen::findOrFail($match->id_turnamen)->resolveKategori()->id;

        $grup1 = Grup::where('id_kategori', $kategoriId)->where('id', $match->id_grup1)->first();
        $grup2 = Grup::where('id_kategori', $kategoriId)->where('id', $match->id_grup2)->first();

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

    public function resetGroupsAndMatches(Turnamen $turnamen, $idKategori = null): void
    {
        if (! $this->canReset($turnamen, $idKategori)) {
            throw new RuntimeException('Grup Friendly tidak dapat di-reset karena sudah ada pertandingan selesai.');
        }

        DB::transaction(function () use ($turnamen, $idKategori) {
            $kategori = $turnamen->resolveKategori($idKategori);
            $grupIds = $kategori->grup()->pluck('id');

            Pertandingan::where('id_kategori', $kategori->id)
                ->where('nama_ronde', 'Friendly')
                ->delete();

            if ($grupIds->isNotEmpty()) {
                GrupMember::whereIn('id_grup', $grupIds)->delete();
                Grup::whereIn('id', $grupIds)->delete();
            }
        });
    }

    public function getMatches(Turnamen $turnamen, $idKategori = null): Collection
    {
        $matches = Pertandingan::query()
            ->where('id_kategori', $turnamen->resolveKategori($idKategori)->id)
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
            ->get();

        return $this->sortMatchesByParallelRounds($matches, $turnamen);
    }

    /**
     * Sort matches into sessions where each session's fixtures use distinct groups
     * (so they can be played at the same time). Uses circle method for pairing order.
     *
     * @param  Collection<int, Pertandingan>  $matches
     * @return Collection<int, Pertandingan>
     */
    public function sortMatchesByParallelRounds(Collection $matches, ?Turnamen $turnamen = null): Collection
    {
        if ($matches->isEmpty()) {
            return $matches;
        }

        $orderedGroupIds = $this->resolveOrderedGroupIdsForSchedule($matches, $turnamen);
        $roundMap = $this->buildParallelRoundMap($orderedGroupIds);

        $annotated = $matches->map(function (Pertandingan $match) use ($roundMap) {
            $a = (int) $match->id_grup1;
            $b = (int) $match->id_grup2;
            $key = min($a, $b) . '-' . max($a, $b);
            $round = $roundMap[$key] ?? null;
            $match->setAttribute('parallel_round', $round);

            return $match;
        });

        $known = $annotated->filter(fn (Pertandingan $match) => $match->parallel_round !== null)
            ->sortBy([
                ['parallel_round', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $unknown = $annotated->filter(fn (Pertandingan $match) => $match->parallel_round === null)
            ->sortBy('id')
            ->values();

        if ($unknown->isNotEmpty()) {
            $nextRound = (int) ($known->max('parallel_round') ?: 0) + 1;
            $packed = $this->packMatchesIntoParallelRounds($unknown, $nextRound);
            $known = $known->concat($packed)->values();
        }

        return $known->values();
    }

    /**
     * @param  Collection<int, Pertandingan>  $matches
     * @return array<int, int>
     */
    protected function resolveOrderedGroupIdsForSchedule(Collection $matches, ?Turnamen $turnamen): array
    {
        if ($turnamen) {
            $ids = $turnamen->competitionGrup()->orderBy('nama')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($ids !== []) {
                return $ids;
            }
        }

        return $matches
            ->flatMap(fn (Pertandingan $match) => [(int) $match->id_grup1, (int) $match->id_grup2])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Map "minGrupId-maxGrupId" => session number (1-based).
     *
     * @param  array<int, int>  $orderedGroupIds
     * @return array<string, int>
     */
    protected function buildParallelRoundMap(array $orderedGroupIds): array
    {
        $count = count($orderedGroupIds);

        if ($count < 2) {
            return [];
        }

        $teams = $count % 2 === 0
            ? $this->seatForConsecutiveOpening($orderedGroupIds)
            : array_merge($orderedGroupIds, [null]);

        $n = count($teams);
        $map = [];
        $round = 1;

        for ($r = 0; $r < $n - 1; $r++) {
            for ($i = 0; $i < intdiv($n, 2); $i++) {
                $a = $teams[$i];
                $b = $teams[$n - 1 - $i];

                if ($a === null || $b === null) {
                    continue;
                }

                $key = min((int) $a, (int) $b) . '-' . max((int) $a, (int) $b);

                if (! isset($map[$key])) {
                    $map[$key] = $round;
                }
            }

            $fixed = $teams[0];
            $rest = array_slice($teams, 1);
            array_unshift($rest, array_pop($rest));
            $teams = array_merge([$fixed], $rest);
            $round++;
        }

        return $map;
    }

    /**
     * Seat teams so the first circle round is consecutive pairs: A-B, C-D, E-F, ...
     *
     * @param  array<int, int>  $orderedGroupIds
     * @return array<int, int>
     */
    protected function seatForConsecutiveOpening(array $orderedGroupIds): array
    {
        $left = [];
        $right = [];
        $n = count($orderedGroupIds);

        for ($i = 0; $i < $n; $i += 2) {
            $left[] = $orderedGroupIds[$i];
        }

        for ($i = $n - 1; $i > 0; $i -= 2) {
            $right[] = $orderedGroupIds[$i];
        }

        return array_merge($left, $right);
    }

    /**
     * Greedy pack leftover/extra matches into sessions without shared groups.
     *
     * @param  Collection<int, Pertandingan>  $matches
     * @return Collection<int, Pertandingan>
     */
    protected function packMatchesIntoParallelRounds(Collection $matches, int $startRound): Collection
    {
        $rounds = [];
        $roundBusy = [];

        foreach ($matches as $match) {
            $a = (int) $match->id_grup1;
            $b = (int) $match->id_grup2;
            $assignedRound = null;

            foreach ($rounds as $roundNumber) {
                $busy = $roundBusy[$roundNumber] ?? [];

                if (! in_array($a, $busy, true) && ! in_array($b, $busy, true)) {
                    $assignedRound = $roundNumber;
                    break;
                }
            }

            if ($assignedRound === null) {
                $assignedRound = ($rounds === [] ? $startRound : max($rounds) + 1);
                $rounds[] = $assignedRound;
                $roundBusy[$assignedRound] = [];
            }

            $roundBusy[$assignedRound][] = $a;
            $roundBusy[$assignedRound][] = $b;
            $match->setAttribute('parallel_round', $assignedRound);
        }

        return $matches->sortBy([
            ['parallel_round', 'asc'],
            ['id', 'asc'],
        ])->values();
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
            ->forKategori($turnamen->resolveKategori()->id)
            ->where('id_pemain1', $side1[0])
            ->first();
        $peserta2 = TurnamenPeserta::query()
            ->forKategori($turnamen->resolveKategori()->id)
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
