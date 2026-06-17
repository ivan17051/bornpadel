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
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages()
    {
        return [
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
