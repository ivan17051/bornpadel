<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turnamen extends Model
{
    protected $table = 'turnamen';

    const CREATED_AT = 'doc';
    const UPDATED_AT = 'dom';

    protected $fillable = [
        'nama',
        'harga',
        'syarat',
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

    public function grup()
    {
        return $this->hasMany(Grup::class, 'id_turnamen');
    }

    public function pertandingan()
    {
        return $this->hasMany(Pertandingan::class, 'id_turnamen');
    }
}
