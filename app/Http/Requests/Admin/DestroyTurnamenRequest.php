<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DestroyTurnamenRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules()
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'password.required' => 'Password admin wajib diisi untuk menghapus turnamen.',
        ];
    }
}
