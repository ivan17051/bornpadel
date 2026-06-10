<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PertandinganSkor extends Model
{
    protected $table = 'pertandingan_skor';

    protected $fillable = [
        'id_pertandingan',
        'set_ke',
        'skor_pemain1',
        'skor_pemain2',
    ];

    public function pertandingan()
    {
        return $this->belongsTo(Pertandingan::class, 'id_pertandingan');
    }
}
