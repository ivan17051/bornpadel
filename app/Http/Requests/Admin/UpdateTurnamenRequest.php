<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTurnamenRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'harga' => ['required', 'numeric', 'min:0'],
            'syarat' => ['nullable', 'string'],
            'jenis' => ['required', 'in:single,double'],
            'status' => ['required', 'in:draft,open,ongoing,completed'],
        ];
    }

    public function messages()
    {
        return [
            'nama.required' => 'Nama turnamen wajib diisi.',
            'harga.required' => 'Biaya pendaftaran wajib diisi.',
            'harga.min' => 'Biaya pendaftaran tidak boleh negatif.',
            'status.in' => 'Status turnamen tidak valid.',
            'jenis.in' => 'Jenis turnamen tidak valid.',
        ];
    }
}
