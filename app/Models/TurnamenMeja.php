<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TurnamenMeja extends Model
{
    protected $table = 'turnamen_meja';

    protected $fillable = [
        'id_turnamen',
        'id_kategori',
        'nama',
        'babak',
        'ronde',
        'is_aktif',
    ];

    protected $casts = [
        'babak' => 'integer',
        'ronde' => 'integer',
        'is_aktif' => 'boolean',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function kategori()
    {
        return $this->belongsTo(TurnamenKategori::class, 'id_kategori');
    }

    public function seats()
    {
        return $this->hasMany(TurnamenMejaSeat::class, 'id_meja')->orderBy('seat_order')->orderBy('id');
    }

    public function poinEntries()
    {
        return $this->hasMany(MahjongPoinEntry::class, 'id_meja')->orderBy('id');
    }

    /**
     * Seat occupants in seat order.
     *
     * @return \Illuminate\Support\Collection<int, GrupMember>
     */
    public function seatedMembers()
    {
        $this->loadMissing('seats.grupMember');

        return $this->seats
            ->map(fn (TurnamenMejaSeat $seat) => $seat->grupMember)
            ->filter()
            ->values();
    }

    /**
     * Group this meja's poin entries into scoring rounds (one hand per row).
     *
     * @return list<array<int, MahjongPoinEntry|null>>
     */
    public function scoringRounds(): array
    {
        $members = $this->seatedMembers();
        if ($members->isEmpty()) {
            return [];
        }

        $memberIds = $members->map(fn (GrupMember $member) => (int) $member->id)->all();
        $entries = $this->relationLoaded('poinEntries')
            ? $this->poinEntries
            : $this->poinEntries()->get();

        $items = [];
        foreach ($entries as $entry) {
            $memberId = (int) $entry->id_grup_member;
            if (! in_array($memberId, $memberIds, true)) {
                continue;
            }

            $items[] = [
                'member_id' => $memberId,
                'entry' => $entry,
                'ts' => optional($entry->created_at)->getTimestamp() ?? 0,
                'id' => (int) $entry->id,
            ];
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }
}
