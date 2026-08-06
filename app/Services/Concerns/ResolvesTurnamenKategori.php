<?php

namespace App\Services\Concerns;

use App\Models\Turnamen;
use App\Models\TurnamenKategori;

trait ResolvesTurnamenKategori
{
    /**
     * Resolve the competition category for an event (default when $idKategori is null).
     *
     * @param  int|string|null  $idKategori
     */
    protected function resolveCompetitionKategori(Turnamen $turnamen, $idKategori = null): TurnamenKategori
    {
        return $turnamen->resolveKategori($idKategori);
    }

    /**
     * Persist lifecycle / capacity fields on kategori.
     * Also mirrors onto the parent turnamen while competitions remain single-category
     * so existing admin/guest code reading turnamen flags keeps working.
     */
    protected function updateCompetitionLifecycle(TurnamenKategori $kategori, array $attributes): TurnamenKategori
    {
        $kategori->fill($attributes);
        $kategori->save();

        $turnamen = $kategori->relationLoaded('turnamen')
            ? $kategori->turnamen
            : $kategori->turnamen()->first();

        if (! $turnamen) {
            return $kategori->fresh();
        }

        $mirrorKeys = [
            'status',
            'registration_paired_at',
            'group_matches_generated_at',
            'mahjong_is_final',
            'harga',
            'maks_peserta',
            'players_per_group',
        ];

        $mirror = array_intersect_key($attributes, array_flip($mirrorKeys));

        if ($mirror !== [] && $turnamen->kategori()->count() <= 1) {
            $turnamen->forceFill($mirror)->save();
        }

        return $kategori->fresh();
    }

    protected function kategoriHasGeneratedGroupMatches(TurnamenKategori $kategori): bool
    {
        if ($kategori->group_matches_generated_at !== null) {
            return true;
        }

        return $kategori->pertandingan()
            ->whereNotNull('id_grup')
            ->where('nama_ronde', 'Fase Grup')
            ->exists();
    }

    /**
     * Competition lifecycle after close registration is stored on kategori.
     * Event shell status may stay open when other categories are still open.
     *
     * Single-category: either shell or kategori may say "ongoing" (they usually mirror).
     * Multi-category: only the selected kategori status is authoritative.
     * Completed event or completed kategori always blocks competition ops.
     */
    protected function isCompetitionOngoing(Turnamen $turnamen, $idKategori = null): bool
    {
        $kategori = $this->resolveCompetitionKategori($turnamen, $idKategori);

        if ($kategori->status === 'completed' || $turnamen->status === 'completed') {
            return false;
        }

        if ($turnamen->hasMultipleKategori()) {
            return $kategori->status === 'ongoing';
        }

        return $kategori->status === 'ongoing' || $turnamen->status === 'ongoing';
    }
}
