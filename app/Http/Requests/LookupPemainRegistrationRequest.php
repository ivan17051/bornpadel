<?php

namespace App\Http\Requests;

use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Http\FormRequest;

class LookupPemainRegistrationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
        ];

        $turnamen = app(PemainRegistrationService::class)->getActiveTournament();

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
