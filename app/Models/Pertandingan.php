<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pertandingan extends Model
{
    protected $table = 'pertandingan';

    protected $fillable = [
        'id_turnamen',
        'id_grup',
        'nama_ronde',
        'id_pemain1',
        'id_pemain2',
        'id_pemenang',
        'status',
        'id_next_pertandingan',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function grup()
    {
        return $this->belongsTo(Grup::class, 'id_grup');
    }

    public function pemain1()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain1');
    }

    public function pemain2()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain2');
    }

    public function pemenang()
    {
        return $this->belongsTo(Pemain::class, 'id_pemenang');
    }

    public function nextPertandingan()
    {
        return $this->belongsTo(Pertandingan::class, 'id_next_pertandingan');
    }

    public function skor()
    {
        return $this->hasMany(PertandinganSkor::class, 'id_pertandingan')->orderBy('set_ke');
    }

    public function feederMatches()
    {
        return $this->hasMany(Pertandingan::class, 'id_next_pertandingan');
    }

    public function isKnockout(): bool
    {
        return is_null($this->id_grup)
            && in_array($this->nama_ronde, ['Perempatfinal', 'Semifinal', 'Final'], true);
    }

    public function isReadyForScoring(): bool
    {
        return $this->id_pemain1 && $this->id_pemain2;
    }
}
