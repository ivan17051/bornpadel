<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnamenGrupPendaftaran extends Model
{
    protected $table = 'turnamen_grup_pendaftaran';

    protected $fillable = [
        'id_turnamen',
        'nama',
    ];

    public function turnamen()
    {
        return $this->belongsTo(Turnamen::class, 'id_turnamen');
    }

    public function members()
    {
        return $this->hasMany(TurnamenGrupPendaftaranMember::class, 'id_grup_pendaftaran')
            ->orderBy('urutan');
    }

    public function peserta()
    {
        return $this->belongsToMany(
            TurnamenPeserta::class,
            'turnamen_grup_pendaftaran_member',
            'id_grup_pendaftaran',
            'id_peserta'
        )->withPivot('urutan')->orderByPivot('urutan');
    }

    public function scopeForTurnamen($query, int $turnamenId)
    {
        return $query->where('id_turnamen', $turnamenId);
    }

    public function isFullyApproved(?Turnamen $turnamen = null): bool
    {
        $this->loadMissing(['members.peserta', 'turnamen']);

        $turnamen = $turnamen ?? $this->turnamen;
        $expectedSize = $turnamen
            ? $turnamen->friendlyPlayersPerGroup()
            : Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP;

        if ($this->members->count() !== $expectedSize) {
            return false;
        }

        return $this->members->every(function (TurnamenGrupPendaftaranMember $member) {
            return optional($member->peserta)->status === 'approved';
        });
    }

    public function approvedMembersCount(): int
    {
        $this->loadMissing('members.peserta');

        return $this->members->filter(function (TurnamenGrupPendaftaranMember $member) {
            return optional($member->peserta)->status === 'approved';
        })->count();
    }
}
