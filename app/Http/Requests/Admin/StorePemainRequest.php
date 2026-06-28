<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use App\Models\Turnamen;
use Illuminate\Foundation\Http\FormRequest;

class StorePemainRequest extends FormRequest
{
    use NormalizesPhoneNumbers;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->normalizePhoneFields(['no_hp', 'partner_no_hp']);
    }

    public function rules()
    {
        $rules = [
            'id_turnamen' => ['required', 'exists:m_turnamen,id'],
            'nama' => ['required', 'string', 'max:255'],
            'tgl_lahir' => ['nullable', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'status' => ['required', 'in:pending,approved,rejected,unpaid,paid'],
            'bukti_bayar' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];

        $turnamen = Turnamen::find($this->input('id_turnamen'));

        if ($turnamen && $turnamen->isDouble()) {
            $rules['partner_no_hp'] = ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp'];
            $rules['partner_nama'] = ['required', 'string', 'max:255'];
            $rules['partner_tgl_lahir'] = ['nullable', 'date', 'before:today'];
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
            'partner_tgl_lahir.before' => 'Tanggal lahir pemain 2 harus sebelum hari ini.',
            'partner_gender.required' => 'Jenis kelamin pemain 2 wajib dipilih.',
        ];
    }
}
