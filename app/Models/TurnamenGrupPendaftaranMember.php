<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurnamenGrupPendaftaranMember extends Model
{
    protected $table = 'turnamen_grup_pendaftaran_member';

    protected $fillable = [
        'id_grup_pendaftaran',
        'id_peserta',
        'urutan',
    ];

    public function grupPendaftaran()
    {
        return $this->belongsTo(TurnamenGrupPendaftaran::class, 'id_grup_pendaftaran');
    }

    public function peserta()
    {
        return $this->belongsTo(TurnamenPeserta::class, 'id_peserta');
    }
}
