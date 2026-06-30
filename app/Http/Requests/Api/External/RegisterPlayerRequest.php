<?php

namespace App\Http\Requests\Api\External;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class RegisterPlayerRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'gender' => ['required', 'in:male,female'],
            'tgl_lahir' => ['nullable', 'date', 'before:today'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'status' => ['nullable', 'in:pending,approved,rejected,unpaid,paid'],
        ];
    }
}
