<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupMember extends Model
{
    protected $table = 'grup_member';

    protected $fillable = [
        'id_grup',
        'id_pemain',
        'id_turnamen_peserta',
        'poin_didapat',
        'set_menang',
        'games_menang',
    ];

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

    public function getDisplayNameAttribute(): string
    {
        if ($this->turnamenPeserta) {
            return $this->turnamenPeserta->display_name;
        }

        return optional($this->pemain)->nama ?? '-';
    }
}
