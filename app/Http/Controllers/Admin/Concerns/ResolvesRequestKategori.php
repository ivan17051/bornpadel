<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use Illuminate\Http\Request;

trait ResolvesRequestKategori
{
    /**
     * Resolve competition category from request for a turnamen.
     *
     * @return array{0: TurnamenKategori, 1: int}
     */
    protected function resolveKategoriFromRequest(Request $request, Turnamen $turnamen): array
    {
        $id = $request->filled('id_kategori') ? (int) $request->input('id_kategori') : null;
        $kategori = $turnamen->resolveKategori($id);

        return [$kategori, (int) $kategori->id];
    }

    /**
     * Query params for operasi / listing views that should keep kategori selection.
     *
     * @return array<string, int>
     */
    protected function kategoriQueryParams(Turnamen $turnamen, ?TurnamenKategori $kategori = null): array
    {
        $kategori = $kategori ?: $turnamen->resolveKategori();

        if (! $turnamen->hasMultipleKategori()) {
            return [];
        }

        return ['id_kategori' => (int) $kategori->id];
    }
}
