<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupMember extends Model
{
    protected $table = 'grup_member';

    protected $fillable = [
        'id_grup',
        'id_pemain',
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
}
