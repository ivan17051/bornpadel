<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerPemainRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'nama' => ['required', 'string', 'max:255'],
            'tgl_lahir' => ['nullable', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }
}
