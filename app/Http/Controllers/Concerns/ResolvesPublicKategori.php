<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Turnamen;
use App\Models\TurnamenKategori;
use Illuminate\Http\Request;
use RuntimeException;

trait ResolvesPublicKategori
{
    /**
     * Resolve competition category from public request.
     * When multi-category and id is required but missing, returns null (caller shows selector)
     * unless $require is true (then throws).
     *
     * @param  int|string|null  $idKategori
     */
    protected function resolvePublicKategori(
        Turnamen $turnamen,
        $idKategori = null,
        bool $requireWhenMultiple = false
    ): ?TurnamenKategori {
        $turnamen->loadMissing('kategori');

        $hasMultiple = $turnamen->hasMultipleKategori();
        $idProvided = $idKategori !== null && $idKategori !== '';

        if ($hasMultiple && ! $idProvided) {
            if ($requireWhenMultiple) {
                throw new RuntimeException('Pilih kategori kompetisi terlebih dahulu.');
            }

            return null;
        }

        try {
            return $turnamen->resolveKategori($idProvided ? (int) $idKategori : null);
        } catch (RuntimeException $e) {
            if ($requireWhenMultiple) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * Resolve kategori for APIs: default when single; 422-style exception when multi without id.
     *
     * @param  int|string|null  $idKategori
     */
    protected function resolveApiKategori(Turnamen $turnamen, $idKategori = null): TurnamenKategori
    {
        $idProvided = $idKategori !== null && $idKategori !== '';

        if ($turnamen->hasMultipleKategori() && ! $idProvided) {
            throw new RuntimeException(
                'Parameter id_kategori wajib diisi karena turnamen memiliki lebih dari satu kategori.'
            );
        }

        return $turnamen->resolveKategori($idProvided ? (int) $idKategori : null);
    }

    /**
     * @return array<string, int>
     */
    protected function publicKategoriQuery(Turnamen $turnamen, ?TurnamenKategori $kategori): array
    {
        if (! $turnamen->hasMultipleKategori() || ! $kategori) {
            return [];
        }

        return ['id_kategori' => (int) $kategori->id];
    }

    /**
     * @return array<string, int>
     */
    protected function publicTurnamenQuery(Turnamen $turnamen, ?TurnamenKategori $kategori = null): array
    {
        return array_filter(array_merge(
            ['id_turnamen' => (int) $turnamen->id],
            $this->publicKategoriQuery($turnamen, $kategori)
        ));
    }

    protected function requestKategoriId(Request $request): ?int
    {
        if (! $request->filled('id_kategori')) {
            return null;
        }

        return (int) $request->input('id_kategori');
    }
}
