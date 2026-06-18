<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pemain extends Model
{
    protected $table = 'm_pemain';

    protected $fillable = [
        'nama',
        'tgl_lahir',
        'usia',
        'gender',
        'no_hp',
        'rating',
        'foto',
    ];

    protected $casts = [
        'tgl_lahir' => 'date',
        'rating' => 'decimal:2',
    ];

    public function turnamenPeserta()
    {
        return $this->hasMany(TurnamenPeserta::class, 'id_pemain');
    }

    public function turnamen()
    {
        return $this->belongsToMany(Turnamen::class, 'turnamen_peserta', 'id_pemain', 'id_turnamen')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function getFotoUrlAttribute(): string
    {
        return app(\App\Services\PemainPhotoService::class)->url($this->foto);
    }

    public function pesertaForTurnamen(?Turnamen $turnamen): ?TurnamenPeserta
    {
        if (! $turnamen) {
            return null;
        }

        if ($this->relationLoaded('turnamenPeserta')) {
            return $this->turnamenPeserta->firstWhere('id_turnamen', $turnamen->id);
        }

        return $this->turnamenPeserta()->where('id_turnamen', $turnamen->id)->first();
    }

    public function grupMembers()
    {
        return $this->hasMany(GrupMember::class, 'id_pemain');
    }

    public function grup()
    {
        return $this->belongsToMany(Grup::class, 'grup_member', 'id_pemain', 'id_grup')
            ->withPivot('poin_didapat', 'set_menang', 'games_menang')
            ->withTimestamps();
    }

    public function pertandinganSebagaiPemain1()
    {
        return $this->hasMany(Pertandingan::class, 'id_pemain1');
    }

    public function pertandinganSebagaiPemain2()
    {
        return $this->hasMany(Pertandingan::class, 'id_pemain2');
    }

    public function pertandinganDimenangkan()
    {
        return $this->hasMany(Pertandingan::class, 'id_pemenang');
    }
}
