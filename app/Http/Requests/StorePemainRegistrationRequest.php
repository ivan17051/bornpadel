<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePemainRegistrationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'tgl_lahir' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ];
    }

    public function messages()
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'tgl_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tgl_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid.',
            'rating.numeric' => 'Rating harus berupa angka.',
            'rating.max' => 'Rating maksimal 10.',
        ];
    }
}
