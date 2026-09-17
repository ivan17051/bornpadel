<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnamenMejaSeat extends Model
{
    protected $table = 'turnamen_meja_seat';

    protected $fillable = [
        'id_meja',
        'id_grup_member',
        'seat_order',
    ];

    protected $casts = [
        'seat_order' => 'integer',
    ];

    public function meja()
    {
        return $this->belongsTo(TurnamenMeja::class, 'id_meja');
    }

    public function grupMember()
    {
        return $this->belongsTo(GrupMember::class, 'id_grup_member');
    }
}
