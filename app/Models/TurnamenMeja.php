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
        return $this->hasMany(MahjongPoinEntry::class, 'id_meja');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }
}
