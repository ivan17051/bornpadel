<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TurnamenPeserta extends Model
{
    protected $table = 'turnamen_peserta';

    protected $fillable = [
        'id_turnamen',
        'id_pemain1',
        'id_pemain2',
        'status',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function pemain1()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain1');
    }

    public function pemain2()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain2');
    }

    /** @deprecated Use pemain1() */
    public function pemain()
    {
        return $this->pemain1();
    }

    public function involvesPemain(int $pemainId): bool
    {
        return (int) $this->id_pemain1 === $pemainId
            || ($this->id_pemain2 && (int) $this->id_pemain2 === $pemainId);
    }

    public function pemainIds(): array
    {
        $ids = [(int) $this->id_pemain1];

        if ($this->id_pemain2) {
            $ids[] = (int) $this->id_pemain2;
        }

        return $ids;
    }

    public function scopeInvolvingPemain(Builder $query, int $pemainId): Builder
    {
        return $query->where(function (Builder $builder) use ($pemainId) {
            $builder->where('id_pemain1', $pemainId)
                ->orWhere('id_pemain2', $pemainId);
        });
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForTurnamen($query, int $turnamenId)
    {
        return $query->where('id_turnamen', $turnamenId);
    }
}
