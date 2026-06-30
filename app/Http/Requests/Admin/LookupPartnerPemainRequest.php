<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class LookupPartnerPemainRequest extends FormRequest
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
        return [
            'no_hp' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }
}
