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

    public function canEditGroups(Turnamen $turnamen): bool
    {
        return $turnamen->isFriendly()
            && $turnamen->status === 'ongoing'
            && $turnamen->grup()->exists()
            && ! $this->hasCompletedMatches($turnamen);
    }

    public function canAddMatch(Turnamen $turnamen): bool
    {
        return $turnamen->isFriendly()
            && $turnamen->status === 'ongoing'
            && $turnamen->grup()->count() >= 2;
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
        ];
    }

    /**
     * @return array{groups: Collection, mode: string}
     */
    public function generateGroups(Turnamen $turnamen, string $mode = 'random'): array
    {
        if (! $this->canGenerateGroups($turnamen)) {
            throw new RuntimeException('Grup Friendly belum dapat dibuat. Pastikan turnamen ongoing dan belum punya grup.');
        }

        $entries = $this->getApprovedEntries($turnamen);
        $preview = $this->previewGroupSplit($entries->count());

        if (! $preview) {
            throw new RuntimeException(
                'Friendly membutuhkan minimal 8 pemain approved dan kelipatan 4 (grup berisi 4 pemain).'
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

            return [
                'groups' => $groups,
                'mode' => $mode,
                'group_count' => $preview['group_count'],
            ];
        });
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

        return Pertandingan::create([
            'id_turnamen' => $turnamen->id,
            'id_grup' => null,
            'id_grup1' => $grup1->id,
            'id_grup2' => $grup2->id,
            'nama_ronde' => 'Friendly',
            'id_pemain1' => $side1[0],
            'id_pemain2' => $side2[0],
            'id_pemain1_partner' => $side1[1],
            'id_pemain2_partner' => $side2[1],
            'id_peserta1' => optional($peserta1)->id,
            'id_peserta2' => optional($peserta2)->id,
            'status' => 'scheduled',
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
            ->orderByDesc('id')
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
