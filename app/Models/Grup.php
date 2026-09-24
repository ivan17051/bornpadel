<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTurnamenKategori;
use Illuminate\Database\Eloquent\Model;

class Grup extends Model
{
    use BelongsToTurnamenKategori;
    protected $table = 'grup';

    protected $fillable = [
        'id_turnamen',
        'id_kategori',
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

    public function kategori()
    {
        return $this->belongsTo(TurnamenKategori::class, 'id_kategori');
    }

    public function members()
    {
        return $this->hasMany(GrupMember::class, 'id_grup');
    }

    /**
     * Group this seating's poin entries into scoring rounds (one hand per row).
     *
     * @return list<array<int, MahjongPoinEntry|null>>
     */
    public function scoringRounds(): array
    {
        $this->loadMissing('members.poinEntries');
        $members = $this->members->values();
        if ($members->isEmpty()) {
            return [];
        }

        $memberIds = $members->map(fn (GrupMember $member) => (int) $member->id)->all();
        $items = [];

        foreach ($members as $member) {
            $entries = $member->relationLoaded('poinEntries')
                ? $member->poinEntries
                : $member->poinEntries()->get();

            foreach ($entries as $entry) {
                $items[] = [
                    'member_id' => (int) $member->id,
                    'entry' => $entry,
                    'ts' => optional($entry->created_at)->getTimestamp() ?? 0,
                    'id' => (int) $entry->id,
                ];
            }
        }

        usort($items, function ($a, $b) {
            return $a['ts'] <=> $b['ts'] ?: $a['id'] <=> $b['id'];
        });

        $rounds = [];
        $used = [];
        foreach ($items as $item) {
            if (isset($used[$item['id']])) {
                continue;
            }

            $round = [];
            foreach ($memberIds as $memberId) {
                $round[$memberId] = null;
            }
            $round[$item['member_id']] = $item['entry'];
            $used[$item['id']] = true;

            foreach ($items as $other) {
                if (isset($used[$other['id']])) {
                    continue;
                }
                if ($round[$other['member_id']] !== null) {
                    continue;
                }
                if (abs($other['ts'] - $item['ts']) <= 3) {
                    $round[$other['member_id']] = $other['entry'];
                    $used[$other['id']] = true;
                }
            }

            $rounds[] = $round;
        }

        return $rounds;
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
