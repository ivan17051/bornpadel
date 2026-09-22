<?php

namespace App\Support;

class MahjongScoreInput
{
    /**
     * Empty or omitted poin values are stored as 0.
     *
     * @param  array<int, mixed>  $scores
     * @return array<int, mixed>
     */
    public static function defaultEmptyPoin(array $scores): array
    {
        foreach ($scores as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $scores[$index]['poin'] = self::defaultEmptyScalar($row['poin'] ?? null);
        }

        return $scores;
    }

    /**
     * @param  mixed  $poin
     * @return mixed
     */
    public static function defaultEmptyScalar($poin)
    {
        if (is_string($poin)) {
            $poin = trim($poin);
        }

        return ($poin === '' || $poin === null) ? 0 : $poin;
    }

    public static function isEmpty($poin): bool
    {
        if (is_string($poin)) {
            $poin = trim($poin);
        }

        return $poin === '' || $poin === null;
    }
}
