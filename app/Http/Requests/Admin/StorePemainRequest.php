<?php

namespace App\Http\Requests\Admin;

use App\Models\Turnamen;
use Illuminate\Foundation\Http\FormRequest;

class StorePemainRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'id_turnamen' => ['required', 'exists:m_turnamen,id'],
            'nama' => ['required', 'string', 'max:255'],
            'tgl_lahir' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];

        $turnamen = Turnamen::find($this->input('id_turnamen'));

        if ($turnamen && $turnamen->isDouble()) {
            $rules['partner_no_hp'] = ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp'];
            $rules['partner_nama'] = ['required', 'string', 'max:255'];
            $rules['partner_tgl_lahir'] = ['required', 'date', 'before:today'];
            $rules['partner_gender'] = ['required', 'in:male,female'];
            $rules['partner_rating'] = ['nullable', 'numeric', 'min:0', 'max:10'];
            $rules['partner_foto'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'id_turnamen.required' => 'Turnamen wajib dipilih.',
            'nama.required' => 'Nama wajib diisi.',
            'tgl_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tgl_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid.',
            'status.required' => 'Status pendaftaran wajib dipilih.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
            'partner_no_hp.required' => 'Nomor HP pemain 2 wajib diisi.',
            'partner_no_hp.different' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.',
            'partner_nama.required' => 'Nama pemain 2 wajib diisi.',
            'partner_tgl_lahir.required' => 'Tanggal lahir pemain 2 wajib diisi.',
            'partner_gender.required' => 'Jenis kelamin pemain 2 wajib dipilih.',
        ];
    }
}
