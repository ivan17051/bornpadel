<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class StoreNewPemainToTurnamenRequest extends FormRequest
{
    use NormalizesPhoneNumbers;

    public function authorize()
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation()
    {
        $this->normalizePhoneFields(['no_hp']);
        $this->mergeNullableDates(['tgl_lahir']);
    }

    protected function mergeNullableDates(array $fields): void
    {
        $merge = [];

        foreach ($fields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules()
    {
        return [
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
        ];
    }
}
