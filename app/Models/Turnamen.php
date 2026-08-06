<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turnamen extends Model
{
    protected $table = 'm_turnamen';

    const CREATED_AT = 'doc';
    const UPDATED_AT = 'dom';

    public const DEFAULT_FRIENDLY_PLAYERS_PER_GROUP = 4;

    public const MIN_FRIENDLY_PLAYERS_PER_GROUP = 2;

    protected $fillable = [
        'nama',
        'tanggal',
        'harga',
        'maks_peserta',
        'syarat',
        'foto',
        'jenis',
        'players_per_group',
        'status',
        'mahjong_is_final',
        'registration_paired_at',
        'group_matches_generated_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'harga' => 'decimal:2',
        'players_per_group' => 'integer',
        'doc' => 'datetime',
        'dom' => 'datetime',
        'mahjong_is_final' => 'boolean',
        'registration_paired_at' => 'datetime',
        'group_matches_generated_at' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePublicActive($query)
    {
        return $query->whereIn('status', ['open', 'ongoing'])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'ongoing' THEN 1 ELSE 2 END")
            ->orderByDesc('tanggal');
    }

    public function scopePublicCompleted($query)
    {
        $cutoff = now()->subYear()->startOfDay();

        return $query->where('status', 'completed')
            ->where('tanggal', '>=', $cutoff)
            ->orderByDesc('tanggal');
    }

    public function scopePublicVisible($query)
    {
        $cutoff = now()->subYear()->startOfDay();

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

    public function isRegistrationPaired(): bool
    {
        return $this->playsAsPairs() && $this->registration_paired_at !== null;
    }

    public function isSingle(): bool
    {
        return $this->jenis === 'single';
    }

    public function isDouble(): bool
    {
        return $this->jenis === 'double';
    }

    /**
     * Single and Double both compete as fixed pairs in groups/knockout.
     */
    public function playsAsPairs(): bool
    {
        return $this->isSingle() || $this->isDouble();
    }

    /**
     * Single: solo registration; partners are randomized when registration closes.
     */
    public function randomizesPartners(): bool
    {
        return $this->isSingle();
    }

    /**
     * Double: players may register solo or as a pair; every approved entry
     * must be in a complete pair before registration can close (no auto-random).
     */
    public function requiresPairRegistration(): bool
    {
        return $this->isDouble();
    }

    public function isMahjong(): bool
    {
        return $this->jenis === 'mahjong';
    }

    public function isFriendly(): bool
    {
        return $this->jenis === 'friendly';
    }

    public function allowsGroupRegistration(): bool
    {
        return $this->isFriendly();
    }

    /**
     * Roster size for Group Match (friendly) groups and full-group registration.
     */
    public function friendlyPlayersPerGroup(): int
    {
        if (! $this->isFriendly()) {
            return self::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP;
        }

        $value = (int) ($this->players_per_group ?: self::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP);

        return max(self::MIN_FRIENDLY_PLAYERS_PER_GROUP, $value);
    }

    /**
     * Group size may change only while draft/open and nobody has registered yet.
     */
    public function canEditFriendlyPlayersPerGroup(): bool
    {
        if (! $this->exists) {
            return true;
        }

        if (! in_array($this->status, ['draft', 'open'], true)) {
            return false;
        }

        return ! $this->hasRegistrations();
    }

    public function hasRegistrations(): bool
    {
        if ($this->relationLoaded('turnamenPeserta')) {
            return $this->turnamenPeserta->isNotEmpty();
        }

        return $this->turnamenPeserta()->exists();
    }

    public function usesKnockoutBracket(): bool
    {
        return $this->isSingle() || $this->isDouble();
    }

    public function getJenisLabelAttribute(): string
    {
        if ($this->jenis === 'double') {
            return 'Double';
        }

        if ($this->jenis === 'mahjong') {
            return 'Mahjong';
        }

        if ($this->jenis === 'friendly') {
            return 'Group Match';
        }

        return 'Single';
    }

    public function getFotoUrlAttribute(): ?string
    {
        return app(\App\Services\TurnamenPhotoService::class)->url($this->foto);
    }

    public function getShareImageUrlAttribute(): string
    {
        return app(\App\Services\TurnamenPhotoService::class)->shareUrl($this->foto);
    }

    public function turnamenPeserta()
    {
        return $this->hasMany(TurnamenPeserta::class, 'id_turnamen');
    }

    public function kategori()
    {
        return $this->hasMany(TurnamenKategori::class, 'id_turnamen')->orderBy('urutan')->orderBy('id');
    }

    public function defaultKategori(): ?TurnamenKategori
    {
        if ($this->relationLoaded('kategori')) {
            $default = $this->kategori->firstWhere('is_default', true);

            if ($default) {
                return $default;
            }

            return $this->kategori
                ->sortBy(function (TurnamenKategori $kategori) {
                    return sprintf('%08d-%08d', (int) $kategori->urutan, (int) $kategori->id);
                })
                ->first();
        }

        $default = $this->kategori()->where('is_default', true)->first();

        if ($default) {
            return $default;
        }

        return $this->kategori()->orderBy('urutan')->orderBy('id')->first();
    }

    /**
     * Ensure a default category exists for this event (Phase 0 silent default).
     */
    public function ensureDefaultKategori(): TurnamenKategori
    {
        $existing = $this->defaultKategori();

        if ($existing) {
            return $existing;
        }

        return $this->kategori()->create([
            'nama' => 'Umum',
            'is_default' => true,
            'urutan' => 1,
            'harga' => $this->harga ?? 0,
            'maks_peserta' => $this->maks_peserta,
            'status' => in_array($this->status, ['draft', 'open', 'ongoing', 'completed'], true)
                ? $this->status
                : 'draft',
            'registration_paired_at' => $this->registration_paired_at,
            'group_matches_generated_at' => $this->group_matches_generated_at,
            'mahjong_is_final' => (bool) $this->mahjong_is_final,
            'players_per_group' => $this->players_per_group,
        ]);
    }

    /**
     * Resolve a category for this event. Null id uses the default category.
     *
     * @param  int|string|null  $idKategori
     */
    public function resolveKategori($idKategori = null): TurnamenKategori
    {
        if ($idKategori === null || $idKategori === '') {
            return $this->ensureDefaultKategori();
        }

        $kategori = $this->kategori()->whereKey($idKategori)->first();

        if (! $kategori) {
            throw new \RuntimeException('Kategori tidak ditemukan untuk turnamen ini.');
        }

        return $kategori;
    }

    public function hasMultipleKategori(): bool
    {
        if ($this->relationLoaded('kategori')) {
            return $this->kategori->count() > 1;
        }

        return $this->kategori()->count() > 1;
    }

    protected static function booted()
    {
        static::created(function (Turnamen $turnamen) {
            $turnamen->ensureDefaultKategori();
        });
    }

    public function pasangan()
    {
        return $this->hasMany(TurnamenPasangan::class, 'id_turnamen');
    }

    public function pemain()
    {
        return $this->belongsToMany(Pemain::class, 'turnamen_peserta', 'id_turnamen', 'id_pemain1')
            ->withPivot('status', 'sumber')
            ->withTimestamps();
    }

    public function grup()
    {
        return $this->hasMany(Grup::class, 'id_turnamen');
    }

    /**
     * Groups for one competition category (default when $idKategori is null).
     */
    public function competitionGrup($idKategori = null)
    {
        return $this->resolveKategori($idKategori)->grup();
    }

    public function competitionActiveGrup($idKategori = null)
    {
        return $this->resolveKategori($idKategori)->activeGrup();
    }

    public function competitionPertandingan($idKategori = null)
    {
        return $this->resolveKategori($idKategori)->pertandingan();
    }

    public function competitionPemenang($idKategori = null)
    {
        return $this->resolveKategori($idKategori)->pemenang();
    }

    public function competitionPeserta($idKategori = null)
    {
        return $this->resolveKategori($idKategori)->peserta();
    }

    public function categoryMahjongIsFinal($idKategori = null): bool
    {
        return (bool) $this->resolveKategori($idKategori)->mahjong_is_final;
    }

    public function categoryGroupMatchesGeneratedAt($idKategori = null)
    {
        return $this->resolveKategori($idKategori)->group_matches_generated_at;
    }

    public function categoryMaksPeserta($idKategori = null): ?int
    {
        $value = $this->resolveKategori($idKategori)->maks_peserta;

        return $value === null ? null : (int) $value;
    }

    public function categoryHarga($idKategori = null)
    {
        return $this->resolveKategori($idKategori)->harga;
    }

    public function grupPendaftaran()
    {
        return $this->hasMany(TurnamenGrupPendaftaran::class, 'id_turnamen');
    }

    public function competitionGrupPendaftaran($idKategori = null)
    {
        return $this->resolveKategori($idKategori)->grupPendaftaran();
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
                'pesertaPemenang.pasanganAsPeserta1.peserta2.pemain1',
                'pesertaPemenang.pasanganAsPeserta2.peserta1.pemain1',
                'pemenang',
            ])->first();

        if (! $final || $final->status !== 'completed') {
            return null;
        }

        return $final->winner_label;
    }
}
