<?php

namespace App\Http\Requests\Concerns;

use App\Support\MahjongScoreInput;

trait DefaultsEmptyMahjongPoin
{
    protected function prepareForValidation()
    {
        $payload = [];

        if (is_array($this->input('scores'))) {
            $payload['scores'] = MahjongScoreInput::defaultEmptyPoin($this->input('scores'));
        }

        foreach (['poin', 'poin_didapat'] as $key) {
            if ($this->exists($key) && MahjongScoreInput::isEmpty($this->input($key))) {
                $payload[$key] = 0;
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
