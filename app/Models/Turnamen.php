<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turnamen extends Model
{
    protected $table = 'm_turnamen';

    const CREATED_AT = 'doc';
    const UPDATED_AT = 'dom';

    protected $fillable = [
        'nama',
        'harga',
        'syarat',
        'jenis',
        'status',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
        'doc' => 'datetime',
        'dom' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isRegistrationClosed(): bool
    {
        return in_array($this->status, ['ongoing', 'completed'], true);
    }

    public function isSingle(): bool
    {
        return $this->jenis === 'single';
    }

    public function isDouble(): bool
    {
        return $this->jenis === 'double';
    }

    public function getJenisLabelAttribute(): string
    {
        return $this->jenis === 'double' ? 'Double' : 'Single';
    }

    public function turnamenPeserta()
    {
        return $this->hasMany(TurnamenPeserta::class, 'id_turnamen');
    }

    public function pemain()
    {
        return $this->belongsToMany(Pemain::class, 'turnamen_peserta', 'id_turnamen', 'id_pemain1')
            ->withPivot('status', 'id_pemain2')
            ->withTimestamps();
    }

    public function grup()
    {
        return $this->hasMany(Grup::class, 'id_turnamen');
    }

    public function pertandingan()
    {
        return $this->hasMany(Pertandingan::class, 'id_turnamen');
    }
}
