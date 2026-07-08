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
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
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
            ->orderByDesc('games_menang');
    }

    public function scopeActive($query)
    {
        return $query->where('is_aktif', true);
    }
}
