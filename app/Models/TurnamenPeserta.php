<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnamenPeserta extends Model
{
    protected $table = 'turnamen_peserta';

    protected $fillable = [
        'id_turnamen',
        'id_pemain',
        'status',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function pemain()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForTurnamen($query, int $turnamenId)
    {
        return $query->where('id_turnamen', $turnamenId);
    }
}
