<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnamenKategori extends Model
{
    protected $table = 'turnamen_kategori';

    protected $fillable = [
        'id_turnamen',
        'nama',
        'is_default',
        'urutan',
        'harga',
        'maks_peserta',
        'status',
        'registration_paired_at',
        'group_matches_generated_at',
        'mahjong_is_final',
        'players_per_group',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'harga' => 'decimal:2',
        'maks_peserta' => 'integer',
        'urutan' => 'integer',
        'mahjong_is_final' => 'boolean',
        'players_per_group' => 'integer',
        'registration_paired_at' => 'datetime',
        'group_matches_generated_at' => 'datetime',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function peserta()
    {
        return $this->hasMany(TurnamenPeserta::class, 'id_kategori');
    }

    public function pasangan()
    {
        return $this->hasMany(TurnamenPasangan::class, 'id_kategori');
    }

    public function grup()
    {
        return $this->hasMany(Grup::class, 'id_kategori');
    }

    public function activeGrup()
    {
        return $this->hasMany(Grup::class, 'id_kategori')->where('is_aktif', true);
    }

    public function grupPendaftaran()
    {
        return $this->hasMany(TurnamenGrupPendaftaran::class, 'id_kategori');
    }

    public function pertandingan()
    {
        return $this->hasMany(Pertandingan::class, 'id_kategori');
    }

    public function pemenang()
    {
        return $this->hasMany(TurnamenPemenang::class, 'id_kategori')->orderBy('peringkat');
    }

    public function friendlyPlayersPerGroup(): int
    {
        $value = (int) ($this->players_per_group ?: Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP);

        return max(Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP, $value);
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isRegistrationClosed(): bool
    {
        return in_array($this->status, ['ongoing', 'completed'], true);
    }

    public function isRegistrationPaired(): bool
    {
        return $this->registration_paired_at !== null;
    }

    public function scopeForTurnamen($query, int $turnamenId)
    {
        return $query->where('id_turnamen', $turnamenId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('urutan')->orderBy('id');
    }
}
