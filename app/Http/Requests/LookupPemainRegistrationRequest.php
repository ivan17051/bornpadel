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
        $this->normalizePhoneFields(['no_hp', 'no_hp_2']);
    }

    public function rules()
    {
        $rules = [
            'no_hp' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'id_turnamen' => ['nullable', 'integer', 'exists:m_turnamen,id'],
            'registration_mode' => ['nullable', 'in:single,pair'],
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

            if ($turnamen && ! $turnamen->requiresPairRegistration() && $this->input('registration_mode') === 'pair') {
                $validator->errors()->add(
                    'registration_mode',
                    'Pendaftaran berpasangan hanya tersedia untuk turnamen double.'
                );
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
            'no_hp_2.different' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.',
        ];
    }
}
