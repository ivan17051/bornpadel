<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnamenPasangan extends Model
{
    protected $table = 'turnamen_pasangan';

    protected $fillable = [
        'id_turnamen',
        'id_peserta_1',
        'id_peserta_2',
        'paired_at',
    ];

    protected $casts = [
        'paired_at' => 'datetime',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function peserta1()
    {
        return $this->belongsTo(TurnamenPeserta::class, 'id_peserta_1');
    }

    public function peserta2()
    {
        return $this->belongsTo(TurnamenPeserta::class, 'id_peserta_2');
    }

    public function scopeForTurnamen($query, int $turnamenId)
    {
        return $query->where('id_turnamen', $turnamenId);
    }

    public function involvesPeserta(int $pesertaId): bool
    {
        return (int) $this->id_peserta_1 === $pesertaId
            || (int) $this->id_peserta_2 === $pesertaId;
    }

    public function getDisplayNameAttribute(): string
    {
        $this->loadMissing(['peserta1.pemain1', 'peserta2.pemain1']);

        $nama1 = optional(optional($this->peserta1)->pemain1)->nama;
        $nama2 = optional(optional($this->peserta2)->pemain1)->nama;

        if ($nama1 && $nama2) {
            return trim($nama1 . ' / ' . $nama2);
        }

        return $nama1 ?: ($nama2 ?: '-');
    }

    public function getEntryPesertaAttribute(): ?TurnamenPeserta
    {
        return $this->peserta1;
    }
}
