<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePemainRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'tgl_lahir' => ['sometimes', 'required', 'date', 'before:today'],
            'gender' => ['sometimes', 'required', 'in:male,female'],
            'no_hp' => ['sometimes', 'required', 'string', 'max:20'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ];
    }
}
