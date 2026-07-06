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
        $this->normalizePhoneFields(['no_hp']);
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
        ];
    }
}
