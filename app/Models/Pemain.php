<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pemain extends Model
{
    protected $table = 'pemain';

    protected $fillable = [
        'nama',
        'tgl_lahir',
        'usia',
        'gender',
        'no_hp',
        'rating',
        'foto',
        'status',
    ];

    protected $casts = [
        'tgl_lahir' => 'date',
        'rating' => 'decimal:2',
    ];

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
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
