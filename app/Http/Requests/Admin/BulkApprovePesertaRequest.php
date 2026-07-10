<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkApprovePesertaRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules()
    {
        return [
            'id_turnamen' => ['required', 'integer', 'exists:m_turnamen,id'],
            'peserta_ids' => ['required', 'array', 'min:1'],
            'peserta_ids.*' => ['integer', 'distinct', 'exists:turnamen_peserta,id'],
        ];
    }

    public function messages()
    {
        return [
            'peserta_ids.required' => 'Daftar peserta wajib diisi.',
            'peserta_ids.min' => 'Pilih minimal satu peserta untuk disetujui.',
        ];
    }
}
