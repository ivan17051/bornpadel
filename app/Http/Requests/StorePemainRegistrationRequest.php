<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Http\FormRequest;

class StorePemainRegistrationRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:255'],
            'tgl_lahir' => ['nullable', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'no_hp' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'bukti_bayar' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ];

        $turnamen = app(PemainRegistrationService::class)->resolveOpenTournament(
            $this->input('id_turnamen') ? (int) $this->input('id_turnamen') : null
        );

        if (! $turnamen) {
            $openCount = app(PemainRegistrationService::class)->getOpenTournaments()->count();
            if ($openCount > 1) {
                $rules['id_turnamen'] = ['required', 'integer', 'exists:m_turnamen,id'];
            } else {
                $turnamen = app(PemainRegistrationService::class)->getActiveTournament();
            }
        }

        if ($turnamen && $turnamen->isDouble()) {
            $rules['partner_no_hp'] = ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp'];
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
            'nama.required' => 'Nama wajib diisi.',
            'tgl_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid.',
            'rating.numeric' => 'Rating harus berupa angka.',
            'rating.max' => 'Rating maksimal 10.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
            'partner_no_hp.required' => 'Nomor HP pemain 2 wajib diisi.',
            'partner_no_hp.regex' => 'Format nomor HP pemain 2 tidak valid.',
            'partner_no_hp.different' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.',
            'partner_nama.required' => 'Nama pemain 2 wajib diisi.',
            'partner_tgl_lahir.before' => 'Tanggal lahir pemain 2 harus sebelum hari ini.',
            'partner_gender.required' => 'Jenis kelamin pemain 2 wajib dipilih.',
            'partner_gender.in' => 'Jenis kelamin pemain 2 tidak valid.',
            'partner_rating.numeric' => 'Rating pemain 2 harus berupa angka.',
            'partner_rating.max' => 'Rating pemain 2 maksimal 10.',
            'partner_foto.image' => 'Foto pemain 2 harus berupa gambar.',
            'partner_foto.mimes' => 'Foto pemain 2 harus berformat JPG, PNG, atau WebP.',
            'partner_foto.max' => 'Ukuran foto pemain 2 maksimal 5 MB.',
        ];
    }
}
