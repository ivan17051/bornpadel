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
        'tanggal',
        'harga',
        'syarat',
        'jenis',
        'status',
        'mahjong_is_final',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'harga' => 'decimal:2',
        'doc' => 'datetime',
        'dom' => 'datetime',
        'mahjong_is_final' => 'boolean',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePublicVisible($query)
    {
        $cutoff = now()->subDays(30)->startOfDay();

        return $query->where(function ($builder) use ($cutoff) {
            $builder->whereIn('status', ['open', 'ongoing'])
                ->orWhere(function ($completed) use ($cutoff) {
                    $completed->where('status', 'completed')
                        ->where('tanggal', '>=', $cutoff);
                });
        })->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'ongoing' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('tanggal');
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

    public function isMahjong(): bool
    {
        return $this->jenis === 'mahjong';
    }

    public function getJenisLabelAttribute(): string
    {
        if ($this->jenis === 'double') {
            return 'Double';
        }

        if ($this->jenis === 'mahjong') {
            return 'Mahjong';
        }

        return 'Single';
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

    public function pemenang()
    {
        return $this->hasMany(TurnamenPemenang::class, 'id_turnamen')->orderBy('peringkat');
    }

    public function activeGrup()
    {
        return $this->hasMany(Grup::class, 'id_turnamen')->where('is_aktif', true);
    }

    public function finalMatch()
    {
        return $this->hasOne(Pertandingan::class, 'id_turnamen')
            ->whereNull('id_grup')
            ->where('nama_ronde', 'Final')
            ->latestOfMany('id');
    }

    public function getChampionLabelAttribute(): ?string
    {
        if ($this->status !== 'completed') {
            return null;
        }

        if ($this->isMahjong()) {
            $juara = $this->relationLoaded('pemenang')
                ? $this->pemenang->firstWhere('peringkat', 1)
                : $this->pemenang()->where('peringkat', 1)->with('pemain')->first();

            return optional(optional($juara)->pemain)->nama;
        }

        $final = $this->relationLoaded('finalMatch')
            ? $this->finalMatch
            : $this->finalMatch()->with([
                'pesertaPemenang.pemain1',
                'pesertaPemenang.pemain2',
                'pemenang',
            ])->first();

        if (! $final || $final->status !== 'completed') {
            return null;
        }

        return $final->winner_label;
    }
}
