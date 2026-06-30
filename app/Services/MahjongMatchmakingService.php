<?php

namespace App\Services;

use App\Models\Grup;
use App\Models\GrupMember;
use App\Models\Turnamen;
use App\Models\TurnamenPeserta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MahjongMatchmakingService
{
    const PLAYERS_PER_GROUP = 4;

    public function canGenerateGroups(Turnamen $turnamen): bool
    {
        return $turnamen->isMahjong()
            && $turnamen->status === 'ongoing'
            && ! $turnamen->activeGrup()->exists();
    }

    public function canReshuffle(Turnamen $turnamen): bool
    {
        return $turnamen->isMahjong()
            && $turnamen->status === 'ongoing'
            && $turnamen->activeGrup()->exists()
            && ! $turnamen->mahjong_is_final;
    }

    public function canAdvanceRound(Turnamen $turnamen): bool
    {
        return $turnamen->isMahjong()
            && $turnamen->status === 'ongoing'
            && $turnamen->activeGrup()->exists()
            && ! $turnamen->mahjong_is_final;
    }

    public function canComplete(Turnamen $turnamen): bool
    {
        return $turnamen->isMahjong()
            && $turnamen->status === 'ongoing'
            && $turnamen->mahjong_is_final
            && $turnamen->activeGrup()->count() === 1
            && $turnamen->activeGrup()->first()->members()->count() === self::PLAYERS_PER_GROUP;
    }

    public function generateGroups(Turnamen $turnamen, string $mode = 'random'): array
    {
        if (! $this->canGenerateGroups($turnamen)) {
            throw new RuntimeException('Grup Mahjong tidak dapat dibuat pada status turnamen ini.');
        }

        $entries = $this->getApprovedEntries($turnamen);
        $this->assertDivisibleByFour($entries->count());

        return $this->createGroupsFromEntries($turnamen, $entries, 1, $mode);
    }

    public function reshuffleGroups(Turnamen $turnamen, string $mode = 'random'): array
    {
        if (! $this->canReshuffle($turnamen)) {
            throw new RuntimeException('Grup Mahjong tidak dapat diacak ulang pada status turnamen ini.');
        }

        return DB::transaction(function () use ($turnamen, $mode) {
            $entries = $this->collectEntriesFromActiveGroups($turnamen);
            $babak = (int) $turnamen->activeGrup()->max('babak') ?: 1;

            $this->deactivateActiveGroups($turnamen);

            return $this->createGroupsFromEntries($turnamen, $entries, $babak, $mode);
        });
    }

    public function advanceRound(Turnamen $turnamen, int $jumlahLolos): array
    {
        if (! $this->canAdvanceRound($turnamen)) {
            throw new RuntimeException('Babak Mahjong tidak dapat dilanjutkan.');
        }

        if ($jumlahLolos < self::PLAYERS_PER_GROUP) {
            throw new RuntimeException('Minimal ' . self::PLAYERS_PER_GROUP . ' pemain untuk babak selanjutnya.');
        }

        return DB::transaction(function () use ($turnamen, $jumlahLolos) {
            $this->commitCurrentRoundPoints($turnamen);

            $qualifiers = $this->getGlobalRankings($turnamen)->take($jumlahLolos)->values();

            if ($qualifiers->count() < self::PLAYERS_PER_GROUP) {
                throw new RuntimeException('Pemain lolos tidak cukup untuk membentuk grup.');
            }

            if ($qualifiers->count() > self::PLAYERS_PER_GROUP
                && $qualifiers->count() % self::PLAYERS_PER_GROUP !== 0) {
                throw new RuntimeException('Jumlah pemain lolos harus kelipatan ' . self::PLAYERS_PER_GROUP . '.');
            }

            $babak = ((int) $turnamen->activeGrup()->max('babak') ?: 0) + 1;
            $this->deactivateActiveGroups($turnamen);

            $isFinal = $qualifiers->count() === self::PLAYERS_PER_GROUP;
            $entries = $qualifiers->map(function (array $row) {
                return $row['peserta'];
            });

            $result = $this->createGroupsFromEntries($turnamen, $entries, $babak, 'by_points', false);
            $turnamen->update(['mahjong_is_final' => $isFinal]);

            $result['is_final'] = $isFinal;
            $result['babak'] = $babak;
            $result['qualifiers'] = $qualifiers->count();

            return $result;
        });
    }

    public function updateMemberPoints(GrupMember $member, int $poinDidapat): GrupMember
    {
        $member->update(['poin_didapat' => max(0, $poinDidapat)]);

        return $member->fresh();
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

    public function getGlobalRankings(Turnamen $turnamen): Collection
    {
        $rows = collect();

        foreach ($this->getActiveMembers($turnamen) as $member) {
            $rows->push([
                'peserta' => $member->turnamenPeserta,
                'pemain' => $member->pemain,
                'member' => $member,
                'total_poin' => $member->total_poin,
            ]);
        }

        return $rows->sortByDesc('total_poin')->values();
    }

    public function getGroupStandingsPayload(Turnamen $turnamen): array
    {
        $groups = $turnamen->activeGrup()
            ->with(['members.pemain', 'members.turnamenPeserta'])
            ->orderBy('nama')
            ->get();

        return [
            'turnamen' => [
                'id' => $turnamen->id,
                'nama' => $turnamen->nama,
                'jenis' => $turnamen->jenis,
                'status' => $turnamen->status,
                'mahjong_is_final' => (bool) $turnamen->mahjong_is_final,
            ],
            'groups' => $groups->map(function (Grup $grup) {
                return [
                    'id' => $grup->id,
                    'nama' => $grup->nama,
                    'babak' => $grup->babak,
                    'members' => $grup->orderedStandings()->get()->map(function (GrupMember $member) {
                        return [
                            'id_pemain' => $member->id_pemain,
                            'id_peserta' => $member->id_turnamen_peserta,
                            'nama' => $member->display_name,
                            'poin_babak' => (int) $member->poin_didapat,
                            'poin_akumulasi' => (int) $member->poin_akumulasi,
                            'total_poin' => $member->total_poin,
                        ];
                    })->values(),
                ];
            })->values(),
            'global_rankings' => $this->getGlobalRankings($turnamen)->map(function (array $row) {
                return [
                    'id_pemain' => optional($row['pemain'])->id,
                    'id_peserta' => optional($row['peserta'])->id,
                    'nama' => optional($row['pemain'])->nama,
                    'total_poin' => $row['total_poin'],
                ];
            })->values(),
        ];
    }

    public function resolveFinalPlacements(Turnamen $turnamen): array
    {
        $finalGroup = $turnamen->activeGrup()->with('members.pemain', 'members.turnamenPeserta')->first();

        if (! $finalGroup) {
            throw new RuntimeException('Grup final Mahjong tidak ditemukan.');
        }

        $ranked = $finalGroup->orderedStandings()->get();
        $placements = [];

        foreach ([1, 2, 3] as $index => $place) {
            $member = $ranked->get($index);

            if (! $member) {
                continue;
            }

            $placements[$place] = [
                'pemain_ids' => [$member->id_pemain],
                'peserta_id' => $member->id_turnamen_peserta,
                'total_poin' => $member->total_poin,
            ];
        }

        return $placements;
    }

    protected function createGroupsFromEntries(
        Turnamen $turnamen,
        Collection $entries,
        int $babak,
        string $mode,
        bool $resetRoundPoints = true
    ): array {
        $ordered = $this->orderEntries($entries, $mode);
        $chunks = $ordered->chunk(self::PLAYERS_PER_GROUP)->values();
        $result = ['groups' => [], 'babak' => $babak, 'mode' => $mode];

        foreach ($chunks as $index => $groupEntries) {
            $grup = Grup::create([
                'id_turnamen' => $turnamen->id,
                'nama' => 'Grup ' . $this->groupLabel($index + 1),
                'babak' => $babak,
                'is_aktif' => true,
            ]);

            foreach ($groupEntries as $entry) {
                /** @var TurnamenPeserta $entry */
                $akumulasi = $resetRoundPoints ? 0 : (int) ($entry->mahjong_carry_points ?? 0);

                GrupMember::create([
                    'id_grup' => $grup->id,
                    'id_pemain' => $entry->id_pemain1,
                    'id_turnamen_peserta' => $entry->id,
                    'poin_didapat' => 0,
                    'poin_akumulasi' => $akumulasi,
                ]);
            }

            $result['groups'][] = [
                'id' => $grup->id,
                'nama' => $grup->nama,
                'pemain_count' => $groupEntries->count(),
            ];
        }

        return $result;
    }

    protected function collectEntriesFromActiveGroups(Turnamen $turnamen): Collection
    {
        $entries = collect();

        foreach ($this->getActiveMembers($turnamen) as $member) {
            if (! $member->turnamenPeserta) {
                continue;
            }

            $member->turnamenPeserta->mahjong_carry_points = $member->total_poin;
            $entries->push($member->turnamenPeserta);
        }

        $this->assertDivisibleByFour($entries->count());

        return $entries->unique('id')->values();
    }

    public function commitCurrentRoundPoints(Turnamen $turnamen): void
    {
        foreach ($this->getActiveMembers($turnamen) as $member) {
            $member->update([
                'poin_akumulasi' => $member->total_poin,
                'poin_didapat' => 0,
            ]);
        }
    }

    protected function deactivateActiveGroups(Turnamen $turnamen): void
    {
        $turnamen->activeGrup()->update(['is_aktif' => false]);
    }

    protected function getActiveMembers(Turnamen $turnamen): Collection
    {
        return GrupMember::query()
            ->whereHas('grup', function ($query) use ($turnamen) {
                $query->where('id_turnamen', $turnamen->id)->where('is_aktif', true);
            })
            ->with(['pemain', 'turnamenPeserta'])
            ->get();
    }

    protected function orderEntries(Collection $entries, string $mode): Collection
    {
        if ($mode === 'by_points') {
            return $entries->sortByDesc(function ($entry) {
                return $entry->mahjong_carry_points ?? 0;
            })->values();
        }

        if ($mode === 'by_rating') {
            return $entries->sortByDesc(function (TurnamenPeserta $entry) {
                return optional($entry->pemain1)->rating ?? 0;
            })->values();
        }

        return $entries->shuffle()->values();
    }

    protected function assertDivisibleByFour(int $count): void
    {
        if ($count < self::PLAYERS_PER_GROUP) {
            throw new RuntimeException('Minimal ' . self::PLAYERS_PER_GROUP . ' pemain approved diperlukan.');
        }

        if ($count % self::PLAYERS_PER_GROUP !== 0) {
            throw new RuntimeException('Jumlah pemain approved harus kelipatan ' . self::PLAYERS_PER_GROUP . '.');
        }
    }

    protected function groupLabel(int $index): string
    {
        return chr(64 + $index);
    }
}
