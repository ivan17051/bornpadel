<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTurnamenKategori;
use Illuminate\Database\Eloquent\Model;

class TurnamenPemenang extends Model
{
    use BelongsToTurnamenKategori;
    protected $table = 'turnamen_pemenang';

    protected $fillable = [
        'id_turnamen',
        'id_kategori',
        'peringkat',
        'id_pemain',
        'id_turnamen_peserta',
        'total_poin',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function kategori()
    {
        return $this->belongsTo(TurnamenKategori::class, 'id_kategori');
    }

    public function pemain()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain');
    }

    public function turnamenPeserta()
    {
        return $this->belongsTo(TurnamenPeserta::class, 'id_turnamen_peserta');
    }
}
