<?php

namespace App\Http\Requests\Admin;

use App\Services\MatchScoringService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMatchScoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'sets' => ['required', 'array', 'min:2', 'max:3'],
            'sets.*.skor_pemain1' => ['required', 'integer', 'min:0', 'max:99'],
            'sets.*.skor_pemain2' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }

    public function messages()
    {
        return [
            'sets.required' => 'Skor set wajib diisi.',
            'sets.min' => 'Minimal 2 set diperlukan untuk menyelesaikan pertandingan (Best of 3).',
            'sets.max' => 'Maksimal 3 set diperbolehkan.',
            'sets.*.skor_pemain1.required' => 'Skor pemain 1 wajib diisi.',
            'sets.*.skor_pemain2.required' => 'Skor pemain 2 wajib diisi.',
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
