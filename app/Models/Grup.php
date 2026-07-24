<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grup extends Model
{
    protected $table = 'grup';

    protected $fillable = [
        'id_turnamen',
        'nama',
        'babak',
        'ronde',
        'is_aktif',
        'poin_didapat',
        'set_menang',
        'games_menang',
        'stats_reached_at',
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
        'stats_reached_at' => 'datetime',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function members()
    {
        return $this->hasMany(GrupMember::class, 'id_grup');
    }

    public function pemain()
    {
        return $this->belongsToMany(Pemain::class, 'grup_member', 'id_grup', 'id_pemain')
            ->withPivot('poin_didapat', 'set_menang', 'games_menang')
            ->withTimestamps();
    }

    public function pertandingan()
    {
        return $this->hasMany(Pertandingan::class, 'id_grup');
    }

    public function friendlyMatchesAsGrup1()
    {
        return $this->hasMany(Pertandingan::class, 'id_grup1');
    }

    public function friendlyMatchesAsGrup2()
    {
        return $this->hasMany(Pertandingan::class, 'id_grup2');
    }

    public function stampStatsReachedAt(): void
    {
        if ($this->stats_reached_at) {
            return;
        }

        $this->update(['stats_reached_at' => now()]);
    }

    public static function compareLeagueRows(array $a, array $b): int
    {
        foreach (['poin_didapat', 'set_menang', 'games_menang'] as $field) {
            $cmp = ((int) ($b[$field] ?? 0)) <=> ((int) ($a[$field] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        $aReached = $a['stats_reached_at'] ?? null;
        $bReached = $b['stats_reached_at'] ?? null;

        if ($aReached && $bReached) {
            return strcmp((string) $aReached, (string) $bReached);
        }

        if ($aReached) {
            return -1;
        }

        if ($bReached) {
            return 1;
        }

        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    }

    public function orderedStandings()
    {
        return $this->members()
            ->with(array_merge(
                ['pemain', 'turnamenPeserta.pemain1'],
                TurnamenPeserta::partnerPemainEagerLoadsFor('turnamenPeserta')
            ))
            ->orderByDesc('poin_akumulasi')
            ->orderByDesc('poin_didapat')
            ->orderByDesc('set_menang')
            ->orderByDesc('games_menang')
            ->orderByRaw('CASE WHEN stats_reached_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('stats_reached_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_aktif', true);
    }
}
