<?php

namespace App\Http\Requests\Api\External;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMahjongScoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'poin' => ['required', 'integer'],
        ];
    }

    public function messages()
    {
        return [
            'poin.required' => 'poin wajib diisi.',
            'poin.integer' => 'poin harus berupa angka.',
        ];
    }
}
