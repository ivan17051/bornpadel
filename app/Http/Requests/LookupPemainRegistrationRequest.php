<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Http\FormRequest;

class LookupPemainRegistrationRequest extends FormRequest
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
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'id_turnamen' => ['nullable', 'integer', 'exists:m_turnamen,id'],
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

        if ($turnamen && $turnamen->isDouble()) {
            $rules['partner_no_hp'] = ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'no_hp.required' => 'Nomor HP pemain 1 wajib diisi.',
            'no_hp.regex' => 'Format nomor HP pemain 1 tidak valid.',
            'partner_no_hp.required' => 'Nomor HP pemain 2 wajib diisi.',
            'partner_no_hp.regex' => 'Format nomor HP pemain 2 tidak valid.',
            'partner_no_hp.different' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.',
        ];
    }
}
