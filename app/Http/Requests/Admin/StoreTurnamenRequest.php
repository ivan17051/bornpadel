<?php

namespace App\Http\Requests\Admin;

use App\Models\Turnamen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTurnamenRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'harga' => ['required', 'numeric', 'min:0'],
            'maks_peserta' => ['nullable', 'integer', 'min:1'],
            'syarat' => ['nullable', 'string'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'jenis' => ['required', 'in:single,double,mahjong,friendly'],
            'status' => ['required', 'in:draft,open,ongoing,completed'],
            'players_per_group' => ['nullable', 'integer', 'min:' . Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP, 'max:255'],
        ];

        if ($this->input('jenis') === 'friendly') {
            $rules['players_per_group'] = [
                'required',
                'integer',
                'min:' . Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP,
                'max:255',
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('jenis') !== 'friendly') {
                return;
            }

            if (! $this->filled('players_per_group')) {
                $validator->errors()->add(
                    'players_per_group',
                    'Jumlah pemain per grup wajib diisi untuk Group Match.'
                );
            }
        });
    }

    public function messages()
    {
        return [
            'nama.required' => 'Nama turnamen wajib diisi.',
            'tanggal.required' => 'Tanggal turnamen wajib diisi.',
            'tanggal.date' => 'Tanggal turnamen tidak valid.',
            'harga.required' => 'Biaya pendaftaran wajib diisi.',
            'harga.min' => 'Biaya pendaftaran tidak boleh negatif.',
            'status.in' => 'Status turnamen tidak valid.',
            'jenis.in' => 'Jenis turnamen tidak valid.',
            'players_per_group.required' => 'Jumlah pemain per grup wajib diisi untuk Group Match.',
            'players_per_group.min' => 'Jumlah pemain per grup minimal ' . Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP . '.',
            'players_per_group.max' => 'Jumlah pemain per grup maksimal 255.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
