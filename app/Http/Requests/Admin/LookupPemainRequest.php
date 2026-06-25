<?php

namespace App\Http\Requests\Admin;

use App\Models\Turnamen;
use Illuminate\Foundation\Http\FormRequest;

class LookupPemainRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'id_turnamen' => ['required', 'exists:m_turnamen,id'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ];

        $turnamen = Turnamen::find($this->input('id_turnamen'));

        if ($turnamen && $turnamen->isDouble()) {
            $rules['partner_no_hp'] = ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'id_turnamen.required' => 'Turnamen wajib dipilih.',
            'no_hp.required' => 'Nomor HP pemain 1 wajib diisi.',
            'no_hp.regex' => 'Format nomor HP pemain 1 tidak valid.',
            'partner_no_hp.required' => 'Nomor HP pemain 2 wajib diisi.',
            'partner_no_hp.regex' => 'Format nomor HP pemain 2 tidak valid.',
            'partner_no_hp.different' => 'Nomor HP pemain 2 harus berbeda dari pemain 1.',
        ];
    }
}
