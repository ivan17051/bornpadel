<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTurnamenKategori;
use Illuminate\Database\Eloquent\Model;

class Pertandingan extends Model
{
    use BelongsToTurnamenKategori;
    protected $table = 'pertandingan';

    protected $fillable = [
        'id_turnamen',
        'id_kategori',
        'id_grup',
        'id_grup1',
        'id_grup2',
        'nama_ronde',
        'id_pemain1',
        'id_pemain2',
        'id_pemain1_partner',
        'id_pemain2_partner',
        'id_peserta1',
        'id_peserta2',
        'id_pemenang',
        'id_peserta_pemenang',
        'status',
        'id_next_pertandingan',
        'id_next_pertandingan_kalah',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function kategori()
    {
        return $this->belongsTo(TurnamenKategori::class, 'id_kategori');
    }

    public function grup()
    {
        return $this->belongsTo(Grup::class, 'id_grup');
    }

    public function grup1()
    {
        return $this->belongsTo(Grup::class, 'id_grup1');
    }

    public function grup2()
    {
        return $this->belongsTo(Grup::class, 'id_grup2');
    }

    public function pemain1()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain1');
    }

    public function pemain2()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain2');
    }

    public function pemain1Partner()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain1_partner');
    }

    public function pemain2Partner()
    {
        return $this->belongsTo(Pemain::class, 'id_pemain2_partner');
    }

    public function peserta1()
    {
        return $this->belongsTo(TurnamenPeserta::class, 'id_peserta1');
    }

    public function peserta2()
    {
        return $this->belongsTo(TurnamenPeserta::class, 'id_peserta2');
    }

    public function pesertaPemenang()
    {
        return $this->belongsTo(TurnamenPeserta::class, 'id_peserta_pemenang');
    }

    public function pemenang()
    {
        return $this->belongsTo(Pemain::class, 'id_pemenang');
    }

    public function nextPertandingan()
    {
        return $this->belongsTo(Pertandingan::class, 'id_next_pertandingan');
    }

    public function nextPertandinganKalah()
    {
        return $this->belongsTo(Pertandingan::class, 'id_next_pertandingan_kalah');
    }

    public function skor()
    {
        return $this->hasMany(PertandinganSkor::class, 'id_pertandingan')->orderBy('set_ke');
    }

    public function feederMatches()
    {
        return $this->hasMany(Pertandingan::class, 'id_next_pertandingan');
    }

    public function isKnockout(): bool
    {
        return is_null($this->id_grup)
            && in_array($this->nama_ronde, ['Babak 16 Besar', 'Perempatfinal', 'Semifinal', 'Final', 'Perebutan Juara 3'], true);
    }

    public function isFriendlyMatch(): bool
    {
        return $this->nama_ronde === 'Friendly'
            || ($this->id_grup1 && $this->id_grup2);
    }

    public function isReadyForScoring(): bool
    {
        if ($this->isFriendlyMatch()) {
            return $this->id_pemain1
                && $this->id_pemain2
                && $this->id_pemain1_partner
                && $this->id_pemain2_partner;
        }

        return $this->id_pemain1 && $this->id_pemain2;
    }

    public function hasFriendlyPairsAssigned(): bool
    {
        return $this->isFriendlyMatch() && $this->isReadyForScoring();
    }

    public function getSide1LabelAttribute(): string
    {
        if ($this->isFriendlyMatch()) {
            return $this->formatPairLabel($this->pemain1, $this->pemain1Partner);
        }

        if ($this->peserta1) {
            return $this->peserta1->display_name;
        }

        return $this->pemain1->nama ?? 'TBD';
    }

    public function getSide2LabelAttribute(): string
    {
        if ($this->isFriendlyMatch()) {
            return $this->formatPairLabel($this->pemain2, $this->pemain2Partner);
        }

        if ($this->peserta2) {
            return $this->peserta2->display_name;
        }

        return $this->pemain2->nama ?? 'TBD';
    }

    public function getWinnerLabelAttribute(): ?string
    {
        if ($this->isFriendlyMatch() && $this->id_pemenang) {
            $side = $this->resolveSideForPemain((int) $this->id_pemenang);

            if ($side === 1) {
                return $this->side1_label;
            }

            if ($side === 2) {
                return $this->side2_label;
            }
        }

        if ($this->pesertaPemenang) {
            return $this->pesertaPemenang->display_name;
        }

        return $this->pemenang->nama ?? null;
    }

    public function resolvePesertaIdForPemain(int $pemainId): ?int
    {
        if ((int) $this->id_pemain1 === $pemainId || (int) $this->id_pemain1_partner === $pemainId) {
            return $this->id_peserta1 ? (int) $this->id_peserta1 : null;
        }

        if ((int) $this->id_pemain2 === $pemainId || (int) $this->id_pemain2_partner === $pemainId) {
            return $this->id_peserta2 ? (int) $this->id_peserta2 : null;
        }

        return null;
    }

    public function resolveSideForPemain(int $pemainId): ?int
    {
        if ((int) $this->id_pemain1 === $pemainId || (int) $this->id_pemain1_partner === $pemainId) {
            return 1;
        }

        if ((int) $this->id_pemain2 === $pemainId || (int) $this->id_pemain2_partner === $pemainId) {
            return 2;
        }

        return null;
    }

    public function resolveWinnerGrupId(): ?int
    {
        if (! $this->id_pemenang) {
            return null;
        }

        $side = $this->resolveSideForPemain((int) $this->id_pemenang);

        if ($side === 1) {
            return $this->id_grup1 ? (int) $this->id_grup1 : null;
        }

        if ($side === 2) {
            return $this->id_grup2 ? (int) $this->id_grup2 : null;
        }

        return null;
    }

    public function resolveLoserGrupId(): ?int
    {
        $winnerGrupId = $this->resolveWinnerGrupId();

        if (! $winnerGrupId) {
            return null;
        }

        if ((int) $this->id_grup1 === $winnerGrupId) {
            return $this->id_grup2 ? (int) $this->id_grup2 : null;
        }

        if ((int) $this->id_grup2 === $winnerGrupId) {
            return $this->id_grup1 ? (int) $this->id_grup1 : null;
        }

        return null;
    }

    protected function formatPairLabel(?Pemain $pemain, ?Pemain $partner): string
    {
        $names = array_values(array_filter([
            optional($pemain)->nama,
            optional($partner)->nama,
        ]));

        return $names !== [] ? implode(' / ', $names) : 'TBD';
    }
}
