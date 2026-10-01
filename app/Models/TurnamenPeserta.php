<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTurnamenKategori;
use App\Services\PaymentReceiptService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TurnamenPeserta extends Model
{
    use BelongsToTurnamenKategori;
    const SUMBER_INTERNAL = 'internal';
    const SUMBER_EXTERNAL = 'external';

    protected $table = 'turnamen_peserta';

    protected $fillable = [
        'id_turnamen',
        'id_kategori',
        'id_pemain1',
        'status',
        'payment_status',
        'sumber',
        'bukti_bayar',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function kategori()
    {
        return $this->belongsTo(TurnamenKategori::class, 'id_kategori');
    }

    public function pemain1()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain1');
    }

    /** @deprecated Use pemain1() */
    public function pemain()
    {
        return $this->pemain1();
    }

    public function pasanganAsPeserta1()
    {
        return $this->hasOne(TurnamenPasangan::class, 'id_peserta_1');
    }

    public function pasanganAsPeserta2()
    {
        return $this->hasOne(TurnamenPasangan::class, 'id_peserta_2');
    }

    public function grupPendaftaranMember()
    {
        return $this->hasOne(TurnamenGrupPendaftaranMember::class, 'id_peserta');
    }

    public function getPasanganAttribute(): ?TurnamenPasangan
    {
        $this->loadMissing(['pasanganAsPeserta1', 'pasanganAsPeserta2']);

        return $this->pasanganAsPeserta1 ?: $this->pasanganAsPeserta2;
    }

    public function getPartnerPesertaAttribute(): ?TurnamenPasangan
    {
        return $this->pasangan;
    }

    /** Eager-load paths for resolving partner pemain via pasangan. */
    public static function partnerPemainEagerLoads(): array
    {
        return [
            'pasanganAsPeserta1.peserta2.pemain1',
            'pasanganAsPeserta2.peserta1.pemain1',
        ];
    }

    public static function partnerPemainEagerLoadsFor(string $prefix = ''): array
    {
        $prefix = $prefix !== '' ? rtrim($prefix, '.') . '.' : '';

        return array_map(
            static fn (string $path) => $prefix . $path,
            self::partnerPemainEagerLoads()
        );
    }

    public function getPartnerPemainAttribute(): ?Pemain
    {
        $pasangan = $this->pasangan;

        if (! $pasangan) {
            return null;
        }

        if ((int) $pasangan->id_peserta_1 === (int) $this->id) {
            return optional($pasangan->peserta2)->pemain1;
        }

        return optional($pasangan->peserta1)->pemain1;
    }

    /** @deprecated Use partner_pemain */
    public function getPemain2Attribute(): ?Pemain
    {
        return $this->partner_pemain;
    }

    public function involvesPemain(int $pemainId): bool
    {
        if ($this->id_pemain1 && (int) $this->id_pemain1 === $pemainId) {
            return true;
        }

        $partner = $this->partner_pemain;

        return $partner && (int) $partner->id === $pemainId;
    }

    public function pemainIds(): array
    {
        $ids = [];

        if ($this->id_pemain1) {
            $ids[] = (int) $this->id_pemain1;
        }

        $partner = $this->partner_pemain;

        if ($partner) {
            $ids[] = (int) $partner->id;
        }

        return array_values(array_unique($ids));
    }

    public function scopeInvolvingPemain(Builder $query, int $pemainId): Builder
    {
        return $query->where(function (Builder $builder) use ($pemainId) {
            $builder->where('id_pemain1', $pemainId)
                ->orWhereHas('pasanganAsPeserta1', function (Builder $pairQuery) use ($pemainId) {
                    $pairQuery->whereHas('peserta2', function (Builder $pesertaQuery) use ($pemainId) {
                        $pesertaQuery->where('id_pemain1', $pemainId);
                    });
                })
                ->orWhereHas('pasanganAsPeserta2', function (Builder $pairQuery) use ($pemainId) {
                    $pairQuery->whereHas('peserta1', function (Builder $pesertaQuery) use ($pemainId) {
                        $pesertaQuery->where('id_pemain1', $pemainId);
                    });
                });
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

    public function scopeForKategori($query, int $kategoriId)
    {
        return $query->where('id_kategori', $kategoriId);
    }

    public function scopeCompletePairs($query)
    {
        return $query->whereHas('pasanganAsPeserta1');
    }

    public function scopeSoloEntries($query)
    {
        return $query->whereDoesntHave('pasanganAsPeserta1')
            ->whereDoesntHave('pasanganAsPeserta2');
    }

    public function isCompletePair(): bool
    {
        return $this->pasangan !== null;
    }

    public function isPaired(): bool
    {
        return $this->isCompletePair();
    }

    public function getDisplayNameAttribute(): string
    {
        $this->loadMissing(['pemain1', 'pasanganAsPeserta1.peserta2.pemain1', 'pasanganAsPeserta2.peserta1.pemain1']);

        $partner = $this->partner_pemain;

        if ($this->pemain1 && $partner) {
            return trim($this->pemain1->nama . ' / ' . $partner->nama);
        }

        if ($this->pemain1) {
            return $this->pemain1->nama;
        }

        return '-';
    }

    public function getAverageRatingAttribute(): float
    {
        $this->loadMissing(array_merge(['pemain1'], self::partnerPemainEagerLoads()));

        $ratings = array_filter([
            optional($this->pemain1)->rating,
            optional($this->partner_pemain)->rating,
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
        return (int) ($this->id_pemain1 ?? 0);
    }

    public function getPairedAtAttribute()
    {
        return optional($this->pasangan)->paired_at;
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];

        return $labels[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return $this->isPaid() ? 'Paid' : 'Unpaid';
    }

    /**
     * Split legacy combined status values (unpaid/paid) from verification.
     *
     * @return array{status: string, payment_status: string}
     */
    public static function normalizeRegistrationState(
        ?string $status = null,
        ?string $paymentStatus = null,
        bool $hasReceipt = false
    ): array {
        $verification = $status;
        $payment = $paymentStatus;

        if ($verification === 'paid') {
            $payment = $payment ?: 'paid';
            $verification = 'pending';
        } elseif ($verification === 'unpaid') {
            $payment = $payment ?: 'unpaid';
            $verification = 'pending';
        }

        if (! in_array($verification, ['pending', 'approved', 'rejected'], true)) {
            $verification = 'pending';
        }

        if (! in_array($payment, ['unpaid', 'paid'], true)) {
            $payment = $hasReceipt ? 'paid' : 'unpaid';
        }

        return [
            'status' => $verification,
            'payment_status' => $payment,
        ];
    }

    public function getSumberLabelAttribute(): string
    {
        return $this->sumber === self::SUMBER_EXTERNAL ? 'Eksternal' : 'Internal';
    }
}
