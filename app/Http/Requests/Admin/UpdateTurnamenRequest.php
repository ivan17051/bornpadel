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
            'tanggal' => ['required', 'date'],
            'harga' => ['required', 'numeric', 'min:0'],
            'maks_peserta' => ['nullable', 'integer', 'min:1'],
            'syarat' => ['nullable', 'string'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_foto' => ['nullable', 'boolean'],
            'jenis' => ['required', 'in:single,double,mahjong,friendly'],
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
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
