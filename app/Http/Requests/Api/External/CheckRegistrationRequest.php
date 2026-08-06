<?php

namespace App\Http\Requests\Api\External;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class CheckRegistrationRequest extends FormRequest
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
            'id_turnamen' => ['required', 'integer', 'exists:m_turnamen,id'],
            'id_kategori' => ['nullable', 'integer', 'exists:turnamen_kategori,id'],
            'no_hp' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }
}
