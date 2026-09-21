<?php

namespace App\Http\Requests\Api\External;

use Illuminate\Foundation\Http\FormRequest;

class StoreMahjongGroupScoresRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id_grup' => ['required_without:id_meja', 'integer'],
            'id_meja' => ['nullable', 'integer'],
            'id_kategori' => ['nullable', 'integer', 'exists:turnamen_kategori,id'],
            'id_grup_member_pemenang' => ['nullable', 'integer'],
            'scores' => ['required', 'array', 'size:4'],
            'scores.*.poin' => ['required', 'integer'],
            'scores.*.id_grup_member' => ['nullable', 'integer'],
            'scores.*.id' => ['nullable', 'integer'],
            'scores.*.id_pemain' => ['nullable', 'integer'],
        ];
    }

    public function messages()
    {
        return [
            'id_grup.required' => 'id_grup atau id_meja wajib diisi.',
            'id_grup.required_without' => 'id_grup atau id_meja wajib diisi.',
            'scores.required' => 'scores wajib diisi.',
            'scores.size' => 'Poin harus diisi untuk keempat pemain dalam grup.',
            'scores.*.poin.required' => 'poin wajib diisi untuk setiap pemain.',
            'scores.*.poin.integer' => 'poin harus berupa angka.',
        ];
    }
}
