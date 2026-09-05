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
}
