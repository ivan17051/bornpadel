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
        'total_poin',
        'foto',
    ];

    protected $casts = [
        'tgl_lahir' => 'date',
        'rating' => 'decimal:2',
        'total_poin' => 'integer',
    ];

    public function turnamenPesertaAsPemain1()
    {
        return $this->hasMany(TurnamenPeserta::class, 'id_pemain1');
    }

    public function turnamenPesertaAsPemain2()
    {
        return $this->hasMany(TurnamenPeserta::class, 'id_pemain2');
    }

    public function turnamen()
    {
        return $this->belongsToMany(Turnamen::class, 'turnamen_peserta', 'id_pemain1', 'id_turnamen')
            ->withPivot('status', 'id_pemain2')
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

        return TurnamenPeserta::query()
            ->involvingPemain($this->id)
            ->where('id_turnamen', $turnamen->id)
            ->first();
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

    public function scopeWithoutRegistration($query)
    {
        return $query
            ->whereDoesntHave('turnamenPesertaAsPemain1')
            ->whereDoesntHave('turnamenPesertaAsPemain2');
    }

    public function scopeWithRegistration($query)
    {
        return $query->where(function ($builder) {
            $builder->whereHas('turnamenPesertaAsPemain1')
                ->orWhereHas('turnamenPesertaAsPemain2');
        });
    }
}
