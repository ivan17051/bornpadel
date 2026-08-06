<?php

namespace App\Models\Concerns;

use App\Models\Turnamen;
use App\Models\TurnamenKategori;

trait BelongsToTurnamenKategori
{
    protected static function bootBelongsToTurnamenKategori(): void
    {
        static::creating(function ($model) {
            if (! empty($model->id_kategori)) {
                if (empty($model->id_turnamen)) {
                    $kategori = TurnamenKategori::query()->find($model->id_kategori);
                    if ($kategori) {
                        $model->id_turnamen = $kategori->id_turnamen;
                    }
                }

                return;
            }

            if (empty($model->id_turnamen)) {
                return;
            }

            $turnamen = Turnamen::query()->find($model->id_turnamen);

            if (! $turnamen) {
                return;
            }

            $kategori = $turnamen->resolveKategori();
            $model->id_kategori = $kategori->id;
        });
    }
}
