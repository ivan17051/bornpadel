<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MahjongPoinEntry extends Model
{
    protected $table = 'mahjong_poin_entry';

    protected $fillable = [
        'id_grup_member',
        'poin',
    ];

    protected $casts = [
        'poin' => 'integer',
    ];

    public function grupMember()
    {
        return $this->belongsTo(GrupMember::class, 'id_grup_member');
    }
}
