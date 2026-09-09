<?php

namespace Tests\Unit;

use App\Services\MahjongStandingRanker;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MahjongStandingRankerTest extends TestCase
{
    public function test_compare_order_is_total_then_wins_then_akumulasi(): void
    {
        $ranker = new MahjongStandingRanker();

        $higherWins = [
            'total_babak' => 10,
            'menang' => 2,
            'poin_akumulasi' => 0,
            'round_scores' => [1, 9],
            'id_peserta' => 2,
        ];
        $higherAkumulasi = [
            'total_babak' => 10,
            'menang' => 1,
            'poin_akumulasi' => 50,
            'round_scores' => [10, 0],
            'id_peserta' => 1,
        ];

        // Same total: more wins ranks higher even with lower akumulasi / last round.
        $this->assertSame(-1, $ranker->compareScores($higherWins, $higherAkumulasi));
        $this->assertSame(-1, $ranker->compare($higherWins, $higherAkumulasi));

        $lowerAkumulasi = [
            'total_babak' => 10,
            'menang' => 1,
            'poin_akumulasi' => 0,
            'id_peserta' => 3,
        ];

        // Same total + wins: higher akumulasi wins; last-round is ignored.
        $this->assertSame(1, $ranker->compareScores($lowerAkumulasi, $higherAkumulasi));
    }

    public function test_resolve_advance_qualifiers_auto_when_cutline_is_clear(): void
    {
        $ranker = new MahjongStandingRanker();
        $rows = new Collection([
            ['id_peserta' => 1, 'nama' => 'A', 'total_babak' => 40, 'menang' => 1, 'poin_akumulasi' => 0],
            ['id_peserta' => 2, 'nama' => 'B', 'total_babak' => 30, 'menang' => 1, 'poin_akumulasi' => 0],
            ['id_peserta' => 3, 'nama' => 'C', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 0],
            ['id_peserta' => 4, 'nama' => 'D', 'total_babak' => 10, 'menang' => 0, 'poin_akumulasi' => 0],
            ['id_peserta' => 5, 'nama' => 'E', 'total_babak' => 5, 'menang' => 0, 'poin_akumulasi' => 0],
        ]);

        $result = $ranker->resolveAdvanceQualifiers($rows, 4);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame([1, 2, 3, 4], $result['qualifiers']->pluck('id_peserta')->all());
    }

    public function test_resolve_advance_qualifiers_uses_wins_before_akumulasi(): void
    {
        $ranker = new MahjongStandingRanker();
        $rows = new Collection([
            ['id_peserta' => 1, 'nama' => 'A', 'total_babak' => 40, 'menang' => 1, 'poin_akumulasi' => 0],
            ['id_peserta' => 2, 'nama' => 'B', 'total_babak' => 20, 'menang' => 3, 'poin_akumulasi' => 0],
            ['id_peserta' => 3, 'nama' => 'C', 'total_babak' => 20, 'menang' => 2, 'poin_akumulasi' => 99],
            ['id_peserta' => 4, 'nama' => 'D', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 99],
            ['id_peserta' => 5, 'nama' => 'E', 'total_babak' => 5, 'menang' => 0, 'poin_akumulasi' => 0],
        ]);

        $result = $ranker->resolveAdvanceQualifiers($rows, 4);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame([1, 2, 3, 4], $result['qualifiers']->pluck('id_peserta')->all());
    }

    public function test_resolve_advance_qualifiers_needs_tiebreak_at_cutline(): void
    {
        $ranker = new MahjongStandingRanker();
        $rows = new Collection([
            ['id_peserta' => 1, 'nama' => 'A', 'total_babak' => 40, 'menang' => 2, 'poin_akumulasi' => 0],
            ['id_peserta' => 2, 'nama' => 'B', 'total_babak' => 30, 'menang' => 1, 'poin_akumulasi' => 0],
            ['id_peserta' => 3, 'nama' => 'C', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
            ['id_peserta' => 4, 'nama' => 'D', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
            ['id_peserta' => 5, 'nama' => 'E', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
            ['id_peserta' => 6, 'nama' => 'F', 'total_babak' => 5, 'menang' => 0, 'poin_akumulasi' => 0],
        ]);

        $result = $ranker->resolveAdvanceQualifiers($rows, 4);

        $this->assertSame('needs_tiebreak', $result['status']);
        $this->assertSame([1, 2], $result['auto_qualified']->pluck('id_peserta')->all());
        $this->assertSame([3, 4, 5], $result['contested']->pluck('id_peserta')->all());
        $this->assertSame(2, $result['slots_remaining']);
    }

    public function test_resolve_advance_qualifiers_accepts_manual_picks(): void
    {
        $ranker = new MahjongStandingRanker();
        $rows = new Collection([
            ['id_peserta' => 1, 'nama' => 'A', 'total_babak' => 40, 'menang' => 1, 'poin_akumulasi' => 0],
            ['id_peserta' => 2, 'nama' => 'B', 'total_babak' => 30, 'menang' => 1, 'poin_akumulasi' => 0],
            ['id_peserta' => 3, 'nama' => 'C', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
            ['id_peserta' => 4, 'nama' => 'D', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
            ['id_peserta' => 5, 'nama' => 'E', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
        ]);

        $result = $ranker->resolveAdvanceQualifiers($rows, 4, [5, 3]);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame([1, 2, 3, 5], $result['qualifiers']->pluck('id_peserta')->all());
    }

    public function test_resolve_advance_qualifiers_rejects_invalid_picks(): void
    {
        $ranker = new MahjongStandingRanker();
        $rows = new Collection([
            ['id_peserta' => 1, 'nama' => 'A', 'total_babak' => 40, 'menang' => 1, 'poin_akumulasi' => 0],
            ['id_peserta' => 2, 'nama' => 'B', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
            ['id_peserta' => 3, 'nama' => 'C', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
            ['id_peserta' => 4, 'nama' => 'D', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
        ]);

        $this->expectException(\RuntimeException::class);
        $ranker->resolveAdvanceQualifiers($rows, 2, [1]);
    }

    public function test_resolve_advance_qualifiers_per_group_takes_top_n_each(): void
    {
        $ranker = new MahjongStandingRanker();
        $grouped = new Collection([
            10 => new Collection([
                ['id_peserta' => 1, 'nama' => 'A', 'grup_nama' => 'Grup A', 'total_babak' => 40, 'menang' => 1, 'poin_akumulasi' => 0],
                ['id_peserta' => 2, 'nama' => 'B', 'grup_nama' => 'Grup A', 'total_babak' => 30, 'menang' => 1, 'poin_akumulasi' => 0],
                ['id_peserta' => 3, 'nama' => 'C', 'grup_nama' => 'Grup A', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 0],
                ['id_peserta' => 4, 'nama' => 'D', 'grup_nama' => 'Grup A', 'total_babak' => 10, 'menang' => 0, 'poin_akumulasi' => 0],
            ]),
            20 => new Collection([
                ['id_peserta' => 5, 'nama' => 'E', 'grup_nama' => 'Grup B', 'total_babak' => 50, 'menang' => 1, 'poin_akumulasi' => 0],
                ['id_peserta' => 6, 'nama' => 'F', 'grup_nama' => 'Grup B', 'total_babak' => 5, 'menang' => 0, 'poin_akumulasi' => 0],
                ['id_peserta' => 7, 'nama' => 'G', 'grup_nama' => 'Grup B', 'total_babak' => 4, 'menang' => 0, 'poin_akumulasi' => 0],
                ['id_peserta' => 8, 'nama' => 'H', 'grup_nama' => 'Grup B', 'total_babak' => 3, 'menang' => 0, 'poin_akumulasi' => 0],
            ]),
        ]);

        $result = $ranker->resolveAdvanceQualifiersPerGroup($grouped, 2);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame([1, 2, 5, 6], $result['qualifiers']->pluck('id_peserta')->all());
    }

    public function test_resolve_advance_qualifiers_per_group_tiebreaks_one_group_at_a_time(): void
    {
        $ranker = new MahjongStandingRanker();
        $grouped = new Collection([
            10 => new Collection([
                ['id_peserta' => 1, 'nama' => 'A', 'grup_nama' => 'Grup A', 'total_babak' => 40, 'menang' => 1, 'poin_akumulasi' => 0],
                ['id_peserta' => 2, 'nama' => 'B', 'grup_nama' => 'Grup A', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
                ['id_peserta' => 3, 'nama' => 'C', 'grup_nama' => 'Grup A', 'total_babak' => 20, 'menang' => 1, 'poin_akumulasi' => 1],
                ['id_peserta' => 4, 'nama' => 'D', 'grup_nama' => 'Grup A', 'total_babak' => 5, 'menang' => 0, 'poin_akumulasi' => 0],
            ]),
            20 => new Collection([
                ['id_peserta' => 5, 'nama' => 'E', 'grup_nama' => 'Grup B', 'total_babak' => 50, 'menang' => 1, 'poin_akumulasi' => 0],
                ['id_peserta' => 6, 'nama' => 'F', 'grup_nama' => 'Grup B', 'total_babak' => 30, 'menang' => 1, 'poin_akumulasi' => 2],
                ['id_peserta' => 7, 'nama' => 'G', 'grup_nama' => 'Grup B', 'total_babak' => 30, 'menang' => 1, 'poin_akumulasi' => 2],
                ['id_peserta' => 8, 'nama' => 'H', 'grup_nama' => 'Grup B', 'total_babak' => 1, 'menang' => 0, 'poin_akumulasi' => 0],
            ]),
        ]);

        $first = $ranker->resolveAdvanceQualifiersPerGroup($grouped, 2);

        $this->assertSame('needs_tiebreak', $first['status']);
        $this->assertSame(10, $first['tiebreak_grup_id']);
        $this->assertSame('Grup A', $first['tiebreak_grup_nama']);
        $this->assertSame([1], $first['auto_qualified']->pluck('id_peserta')->all());
        $this->assertSame([2, 3], $first['contested']->pluck('id_peserta')->all());
        $this->assertSame(1, $first['slots_remaining']);

        $second = $ranker->resolveAdvanceQualifiersPerGroup($grouped, 2, [3]);

        $this->assertSame('needs_tiebreak', $second['status']);
        $this->assertSame(20, $second['tiebreak_grup_id']);
        $this->assertSame('Grup B', $second['tiebreak_grup_nama']);
        $this->assertSame([1, 3, 5], $second['auto_qualified']->pluck('id_peserta')->all());
        $this->assertSame([6, 7], $second['contested']->pluck('id_peserta')->all());
        $this->assertSame(1, $second['slots_remaining']);

        $resolved = $ranker->resolveAdvanceQualifiersPerGroup($grouped, 2, [3, 7]);

        $this->assertSame('resolved', $resolved['status']);
        $this->assertSame([1, 3, 5, 7], $resolved['qualifiers']->pluck('id_peserta')->all());
    }
}
