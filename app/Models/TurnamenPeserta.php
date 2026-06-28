<?php

namespace App\Models;

use App\Services\PaymentReceiptService;
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
        'bukti_bayar',
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
        return ($this->id_pemain1 && (int) $this->id_pemain1 === $pemainId)
            || ($this->id_pemain2 && (int) $this->id_pemain2 === $pemainId);
    }

    public function pemainIds(): array
    {
        $ids = [];

        if ($this->id_pemain1) {
            $ids[] = (int) $this->id_pemain1;
        }

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

    public function scopeEligibleForMatchmaking($query)
    {
        return $query->where('status', 'approved');
    }

    public function isApprovedForMatchmaking(): bool
    {
        return $this->status === 'approved';
    }

    public function getBuktiBayarUrlAttribute(): ?string
    {
        return app(PaymentReceiptService::class)->url($this->bukti_bayar);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForTurnamen($query, int $turnamenId)
    {
        return $query->where('id_turnamen', $turnamenId);
    }

    public function scopeCompletePairs($query)
    {
        return $query->whereNotNull('id_pemain1')->whereNotNull('id_pemain2');
    }

    public function isCompletePair(): bool
    {
        return $this->id_pemain1 !== null && $this->id_pemain2 !== null;
    }

    public function getDisplayNameAttribute(): string
    {
        $this->loadMissing(['pemain1', 'pemain2']);

        if ($this->pemain1 && $this->pemain2) {
            return trim($this->pemain1->nama . ' / ' . $this->pemain2->nama);
        }

        if ($this->pemain1) {
            return $this->pemain1->nama;
        }

        if ($this->pemain2) {
            return $this->pemain2->nama;
        }

        return '-';
    }

    public function getAverageRatingAttribute(): float
    {
        $this->loadMissing(['pemain1', 'pemain2']);

        $ratings = array_filter([
            optional($this->pemain1)->rating,
            optional($this->pemain2)->rating,
        ], static function ($rating) {
            return $rating !== null;
        });

        if ($ratings === []) {
            return 0.0;
        }

        return array_sum($ratings) / count($ratings);
    }

    public function getRepresentativePemainIdAttribute(): int
    {
        return (int) ($this->id_pemain1 ?? $this->id_pemain2 ?? 0);
    }
}
