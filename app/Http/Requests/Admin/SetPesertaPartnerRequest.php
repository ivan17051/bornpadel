<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class SetPesertaPartnerRequest extends FormRequest
{
    use NormalizesPhoneNumbers;

    public function authorize()
    {
        return $this->user() && $this->user()->isAdmin();
    }

    protected function prepareForValidation()
    {
        $this->normalizePhoneFields(['no_hp']);
    }

    public function rules()
    {
        return [
            'partner_peserta_id' => ['nullable', 'integer', 'exists:turnamen_peserta,id', 'required_without_all:no_hp,nama,gender'],
            'nama' => ['nullable', 'string', 'max:255', 'required_without:partner_peserta_id'],
            'no_hp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/', 'required_without:partner_peserta_id'],
            'gender' => ['nullable', 'in:male,female', 'required_without:partner_peserta_id'],
            'tgl_lahir' => ['nullable', 'date', 'before:today'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages()
    {
        return [
            'partner_peserta_id.required_without_all' => 'Pilih peserta pasangan yang sudah ada atau isi data pemain baru.',
            'nama.required_without' => 'Nama pemain pasangan wajib diisi.',
            'no_hp.required_without' => 'Nomor HP pemain pasangan wajib diisi.',
            'gender.required_without' => 'Jenis kelamin pemain pasangan wajib dipilih.',
        ];
    }
}
