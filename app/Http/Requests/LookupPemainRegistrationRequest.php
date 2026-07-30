<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LookupPemainRegistrationRequest extends FormRequest
{
    use NormalizesPhoneNumbers;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->normalizePhoneFields(['no_hp', 'no_hp_2', 'no_hp_3', 'no_hp_4']);
    }

    public function rules()
    {
        $rules = [
            'no_hp' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'id_turnamen' => ['nullable', 'integer', 'exists:m_turnamen,id'],
            'registration_mode' => ['nullable', 'in:single,pair,group'],
        ];

        $turnamen = app(PemainRegistrationService::class)->resolveOpenTournament(
            $this->input('id_turnamen') ? (int) $this->input('id_turnamen') : null
        );

        if (! $turnamen) {
            $openCount = app(PemainRegistrationService::class)->getOpenTournaments()->count();
            if ($openCount > 1) {
                $rules['id_turnamen'] = ['required', 'integer', 'exists:m_turnamen,id'];
            }
        }

        if ($turnamen && $turnamen->requiresPairRegistration()) {
            $rules['registration_mode'] = ['required', 'in:single,pair'];

            if ($this->input('registration_mode') === 'pair') {
                $rules['no_hp_2'] = ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp'];
            }
        } elseif ($turnamen && $turnamen->allowsGroupRegistration()) {
            $rules['registration_mode'] = ['required', 'in:single,group'];

            if ($this->input('registration_mode') === 'group') {
                $rules['nama_grup'] = ['required', 'string', 'max:255'];
                $rules['no_hp_2'] = ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp'];
                $rules['no_hp_3'] = ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp', 'different:no_hp_2'];
                $rules['no_hp_4'] = ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp', 'different:no_hp_2', 'different:no_hp_3'];
            }
        } elseif ($turnamen && $turnamen->randomizesPartners()) {
            $rules['registration_mode'] = ['nullable', 'in:single'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $turnamen = app(PemainRegistrationService::class)->resolveOpenTournament(
                $this->input('id_turnamen') ? (int) $this->input('id_turnamen') : null
            );

            if (! $turnamen) {
                return;
            }

            if ($this->input('registration_mode') === 'pair' && ! $turnamen->requiresPairRegistration()) {
                $validator->errors()->add(
                    'registration_mode',
                    'Pendaftaran berpasangan hanya tersedia untuk turnamen double.'
                );
            }

            if ($this->input('registration_mode') === 'group' && ! $turnamen->allowsGroupRegistration()) {
                $validator->errors()->add(
                    'registration_mode',
                    'Pendaftaran satu grup hanya tersedia untuk Group Match.'
                );
            }

            if ($turnamen->allowsGroupRegistration()
                && $this->input('registration_mode') === 'group'
                && $this->filled('nama_grup')
            ) {
                try {
                    app(PemainRegistrationService::class)->assertGroupNameAvailable(
                        $turnamen,
                        (string) $this->input('nama_grup')
                    );
                } catch (\RuntimeException $e) {
                    $validator->errors()->add('nama_grup', $e->getMessage());
                }
            }
        });
    }

    public function messages()
    {
        return [
            'no_hp.required' => 'Nomor HP pemain 1 wajib diisi.',
            'no_hp.regex' => 'Format nomor HP pemain 1 tidak valid.',
            'no_hp_2.required' => 'Nomor HP pemain 2 wajib diisi.',
            'no_hp_2.regex' => 'Format nomor HP pemain 2 tidak valid.',
            'no_hp_2.different' => 'Nomor HP pemain 2 harus berbeda dari pemain lain.',
            'no_hp_3.required' => 'Nomor HP pemain 3 wajib diisi.',
            'no_hp_3.regex' => 'Format nomor HP pemain 3 tidak valid.',
            'no_hp_3.different' => 'Nomor HP pemain 3 harus berbeda dari pemain lain.',
            'no_hp_4.required' => 'Nomor HP pemain 4 wajib diisi.',
            'no_hp_4.regex' => 'Format nomor HP pemain 4 tidak valid.',
            'no_hp_4.different' => 'Nomor HP pemain 4 harus berbeda dari pemain lain.',
            'nama_grup.required' => 'Nama grup wajib diisi.',
        ];
    }
}
