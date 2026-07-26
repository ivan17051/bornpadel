<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkRegisterPesertaRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'id_turnamen' => ['required', 'integer', 'exists:m_turnamen,id'],
            'pemain_ids' => ['required', 'array', 'min:1'],
            'pemain_ids.*' => ['integer', 'distinct', 'exists:m_pemain,id'],
            'status' => ['nullable', 'in:pending,approved,rejected,unpaid,paid'],
        ];
    }

    public function messages()
    {
        return [
            'pemain_ids.required' => 'Pilih minimal satu pemain.',
            'pemain_ids.min' => 'Pilih minimal satu pemain.',
            'pemain_ids.*.exists' => 'Beberapa pemain tidak ditemukan.',
        ];
    }
}
