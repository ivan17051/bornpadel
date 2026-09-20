<?php

namespace App\Http\Requests\Admin;

use App\Models\Turnamen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTurnamenRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        /** @var Turnamen|null $turnamen */
        $turnamen = $this->route('turnamen');
        $canEditSize = ! $turnamen || $turnamen->canEditRegistrationRosterSize();
        $jenis = $this->input('jenis', optional($turnamen)->jenis);

        $rules = [
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'harga' => ['required', 'numeric', 'min:0'],
            'maks_peserta' => ['nullable', 'integer', 'min:1'],
            'syarat' => ['nullable', 'string'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_foto' => ['nullable', 'boolean'],
            'jenis' => ['required', 'in:single,double,mahjong,friendly,mahjong_team'],
            'status' => ['required', 'in:draft,open,ongoing,completed'],
            'players_per_group' => ['nullable', 'integer', 'min:' . Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP, 'max:255'],
        ];

        if ($jenis === 'friendly' && $canEditSize) {
            $rules['players_per_group'] = [
                'required',
                'integer',
                'min:' . Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP,
                'max:255',
            ];
        } elseif ($jenis === 'mahjong_team' && $canEditSize) {
            $rules['players_per_group'] = [
                'required',
                'integer',
                'min:' . Turnamen::MAHJONG_TEAM_MIN_PLAYERS_PER_TEAM,
                'max:' . Turnamen::MAHJONG_TEAM_MAX_PLAYERS_PER_TEAM,
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Turnamen|null $turnamen */
            $turnamen = $this->route('turnamen');

            if (! $turnamen || ! in_array($this->input('jenis'), ['friendly', 'mahjong_team'], true)) {
                return;
            }

            $isTeam = $this->input('jenis') === 'mahjong_team';
            $currentSize = $isTeam
                ? $turnamen->mahjongPlayersPerTeam()
                : $turnamen->friendlyPlayersPerGroup();
            $lockMessage = $isTeam
                ? 'Jumlah pemain per tim hanya bisa diubah saat status draft/open dan belum ada pendaftaran.'
                : 'Jumlah pemain per grup hanya bisa diubah saat status draft/open dan belum ada pendaftaran.';
            $requiredMessage = $isTeam
                ? 'Jumlah pemain per tim wajib diisi untuk Mahjong Tim.'
                : 'Jumlah pemain per grup wajib diisi untuk Group Match.';

            if (! $turnamen->canEditRegistrationRosterSize()) {
                $incoming = $this->input('players_per_group');
                if ($incoming !== null && (int) $incoming !== (int) $currentSize) {
                    $validator->errors()->add('players_per_group', $lockMessage);
                }

                return;
            }

            if (! $this->filled('players_per_group')) {
                $validator->errors()->add('players_per_group', $requiredMessage);
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
            'players_per_group.required' => $this->input('jenis') === 'mahjong_team'
                ? 'Jumlah pemain per tim wajib diisi untuk Mahjong Tim.'
                : 'Jumlah pemain per grup wajib diisi untuk Group Match.',
            'players_per_group.min' => $this->input('jenis') === 'mahjong_team'
                ? 'Jumlah pemain per tim minimal ' . Turnamen::MAHJONG_TEAM_MIN_PLAYERS_PER_TEAM . '.'
                : 'Jumlah pemain per grup minimal ' . Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP . '.',
            'players_per_group.max' => $this->input('jenis') === 'mahjong_team'
                ? 'Jumlah pemain per tim maksimal ' . Turnamen::MAHJONG_TEAM_MAX_PLAYERS_PER_TEAM . '.'
                : 'Jumlah pemain per grup maksimal 255.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
