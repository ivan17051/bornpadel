<?php

namespace App\Http\Requests\Admin;

use App\Services\MatchScoringService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class StoreMatchScoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $pertandingan = $this->route('pertandingan');
        $isUpdate = $pertandingan && $pertandingan->status === 'completed';

        return [
            'sets' => [
                'required',
                'array',
                'min:' . MatchScoringService::MIN_SETS,
                'max:' . MatchScoringService::MAX_SETS,
            ],
            'sets.*.skor_pemain1' => ['required', 'integer', 'min:0', 'max:99'],
            'sets.*.skor_pemain2' => ['required', 'integer', 'min:0', 'max:99'],
            'password' => $isUpdate
                ? ['required', 'string']
                : ['nullable', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'sets.required' => 'Skor set wajib diisi.',
            'sets.min' => 'Minimal 1 set diperlukan untuk menyelesaikan pertandingan.',
            'sets.max' => 'Maksimal ' . MatchScoringService::MAX_SETS . ' set diperbolehkan.',
            'sets.*.skor_pemain1.required' => 'Skor pemain 1 wajib diisi.',
            'sets.*.skor_pemain2.required' => 'Skor pemain 2 wajib diisi.',
            'password.required' => 'Password wajib diisi untuk mengubah skor.',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $pertandingan = $this->route('pertandingan');
            $sets = $this->input('sets', []);

            if ($pertandingan && $pertandingan->status === 'completed') {
                $user = $this->user();
                $password = (string) $this->input('password', '');

                if (! $user || ! Hash::check($password, $user->password)) {
                    $validator->errors()->add('password', 'Password tidak valid.');
                    return;
                }
            }

            try {
                app(MatchScoringService::class)->calculateMatchResult(
                    $sets,
                    $pertandingan->id_pemain1,
                    $pertandingan->id_pemain2
                );
            } catch (\RuntimeException $e) {
                $validator->errors()->add('sets', $e->getMessage());
            }
        });
    }
}
