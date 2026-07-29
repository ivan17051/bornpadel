<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GrupMember extends Model
{
    protected $table = 'grup_member';

    protected $fillable = [
        'id_grup',
        'id_pemain',
        'id_turnamen_peserta',
        'poin_didapat',
        'poin_akumulasi',
        'set_menang',
        'games_menang',
        'stats_reached_at',
    ];

    protected $casts = [
        'stats_reached_at' => 'datetime',
    ];

    public function getTotalPoinAttribute(): int
    {
        return (int) $this->poin_akumulasi + (int) $this->poin_didapat;
    }

    public function grup()
    {
        return $this->belongsTo(Grup::class, 'id_grup');
    }

    public function pemain()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain');
    }

    public function turnamenPeserta()
    {
        return $this->belongsTo(TurnamenPeserta::class, 'id_turnamen_peserta');
    }

    public function poinEntries()
    {
        return $this->hasMany(MahjongPoinEntry::class, 'id_grup_member')->orderBy('id');
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->turnamenPeserta) {
            return $this->turnamenPeserta->display_name;
        }

        return optional($this->pemain)->nama ?? '-';
    }

    public function scopeOrderedForPadelStandings(Builder $query): Builder
    {
        return $query
            ->orderByDesc('poin_didapat')
            ->orderByDesc('set_menang')
            ->orderByDesc('games_menang')
            ->orderByRaw('CASE WHEN stats_reached_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('stats_reached_at');
    }

    public function stampStatsReachedAt(): void
    {
        $this->update(['stats_reached_at' => now()]);
    }

    /**
     * Compare padel standing rows (higher rank = negative return value for sort asc).
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function comparePadelStandingRows(array $a, array $b): int
    {
        foreach (['poin_didapat', 'set_menang', 'games_menang'] as $field) {
            $left = (int) ($a[$field] ?? 0);
            $right = (int) ($b[$field] ?? 0);

            if ($left !== $right) {
                return $right <=> $left;
            }
        }

        $timeA = self::standingTimestampValue($a['stats_reached_at'] ?? null);
        $timeB = self::standingTimestampValue($b['stats_reached_at'] ?? null);

        if ($timeA !== $timeB) {
            return $timeA <=> $timeB;
        }

        return ((int) ($a['id_peserta'] ?? 0)) <=> ((int) ($b['id_peserta'] ?? 0));
    }

    public static function formatGameDifference($value): string
    {
        $diff = (int) $value;

        return $diff > 0 ? '+' . $diff : (string) $diff;
    }

    protected static function standingTimestampValue($value): int
    {
        if ($value === null || $value === '') {
            return PHP_INT_MAX;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        return strtotime((string) $value) ?: PHP_INT_MAX;
    }
}
