<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTurnamenRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
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
            'tanggal.required' => 'Tanggal turnamen wajib diisi.',
            'tanggal.date' => 'Tanggal turnamen tidak valid.',
            'harga.required' => 'Biaya pendaftaran wajib diisi.',
            'harga.min' => 'Biaya pendaftaran tidak boleh negatif.',
            'status.in' => 'Status turnamen tidak valid.',
            'jenis.in' => 'Jenis turnamen tidak valid.',
        ];
    }
}
