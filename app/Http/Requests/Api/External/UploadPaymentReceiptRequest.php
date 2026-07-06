<?php

namespace App\Http\Requests\Api\External;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentReceiptRequest extends FormRequest
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
            'peserta_id' => ['nullable', 'integer', 'exists:turnamen_peserta,id', 'required_without_all:id_turnamen,no_hp'],
            'id_turnamen' => ['nullable', 'integer', 'exists:m_turnamen,id', 'required_without:peserta_id'],
            'no_hp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/', 'required_without:peserta_id'],
            'bukti_bayar' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages()
    {
        return [
            'peserta_id.required_without_all' => 'Berikan peserta_id atau kombinasi id_turnamen dan no_hp.',
            'id_turnamen.required_without' => 'id_turnamen wajib diisi jika peserta_id tidak dikirim.',
            'no_hp.required_without' => 'no_hp wajib diisi jika peserta_id tidak dikirim.',
        ];
    }
}
