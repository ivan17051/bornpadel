<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LookupPemainRegistrationRequest extends FormRequest
{
    use NormalizesPhoneNumbers;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $fields = ['no_hp'];
        $turnamen = $this->resolveTurnamen();
        $kategori = $this->resolveKategori($turnamen);
        $size = $turnamen && $turnamen->allowsGroupRegistration()
            ? ($kategori ? $kategori->friendlyPlayersPerGroup() : $turnamen->friendlyPlayersPerGroup())
            : 4;

        for ($n = 2; $n <= max(4, $size); $n++) {
            $fields[] = 'no_hp_' . $n;
        }

        $this->normalizePhoneFields($fields);
    }

    public function rules()
    {
        $rules = [
            'no_hp' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'id_turnamen' => ['nullable', 'integer', 'exists:m_turnamen,id'],
            'id_kategori' => ['nullable', 'integer', 'exists:turnamen_kategori,id'],
            'registration_mode' => ['nullable', 'in:single,pair,group'],
        ];

        $turnamen = $this->resolveTurnamen();

        if (! $turnamen) {
            $openCount = app(PemainRegistrationService::class)->getOpenTournaments()->count();
            if ($openCount > 1) {
                $rules['id_turnamen'] = ['required', 'integer', 'exists:m_turnamen,id'];
            }
        } elseif ($turnamen->hasMultipleKategori()) {
            $rules['id_kategori'] = ['required', 'integer', 'exists:turnamen_kategori,id'];
        }

        $kategori = $this->resolveKategori($turnamen);

        if ($turnamen && $turnamen->requiresPairRegistration()) {
            $rules['registration_mode'] = ['required', 'in:single,pair'];

            if ($this->input('registration_mode') === 'pair') {
                $rules['no_hp_2'] = ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/', 'different:no_hp'];
            }
        } elseif ($turnamen && $turnamen->allowsGroupRegistration()) {
            $rules['registration_mode'] = ['required', 'in:single,group'];

            if ($this->input('registration_mode') === 'group') {
                $rules['nama_grup'] = ['required', 'string', 'max:255'];
                $size = $kategori
                    ? $kategori->friendlyPlayersPerGroup()
                    : $turnamen->friendlyPlayersPerGroup();
                $previous = ['no_hp'];

                for ($n = 2; $n <= $size; $n++) {
                    $field = 'no_hp_' . $n;
                    $different = array_map(fn ($prev) => 'different:' . $prev, $previous);
                    $rules[$field] = array_merge(
                        ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
                        $different
                    );
                    $previous[] = $field;
                }
            }
        } elseif ($turnamen && $turnamen->randomizesPartners()) {
            $rules['registration_mode'] = ['nullable', 'in:single'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $turnamen = $this->resolveTurnamen();

            if (! $turnamen) {
                return;
            }

            $kategori = $this->resolveKategori($turnamen);

            if ($turnamen->hasMultipleKategori() && ! $kategori) {
                $validator->errors()->add('id_kategori', 'Pilih kategori kompetisi terlebih dahulu.');

                return;
            }

            if ($this->filled('id_kategori') && $kategori && (int) $kategori->id_turnamen !== (int) $turnamen->id) {
                $validator->errors()->add('id_kategori', 'Kategori tidak valid untuk turnamen ini.');
            }

            if ($this->input('registration_mode') === 'pair' && ! $turnamen->requiresPairRegistration()) {
                $validator->errors()->add(
                    'registration_mode',
                    'Pendaftaran berpasangan hanya tersedia untuk turnamen double.'
                );
            }

            if ($this->input('registration_mode') === 'group' && ! $turnamen->allowsGroupRegistration()) {
                $validator->errors()->add(
                    'registration_mode',
                    'Pendaftaran satu grup hanya tersedia untuk Group Match.'
                );
            }

            if ($turnamen->allowsGroupRegistration()
                && $this->input('registration_mode') === 'group'
                && $this->filled('nama_grup')
                && $kategori
            ) {
                try {
                    app(PemainRegistrationService::class)->assertGroupNameAvailable(
                        $turnamen,
                        (string) $this->input('nama_grup'),
                        $kategori->id
                    );
                } catch (\RuntimeException $e) {
                    $validator->errors()->add('nama_grup', $e->getMessage());
                }
            }
        });
    }

    protected function resolveTurnamen(): ?Turnamen
    {
        return app(PemainRegistrationService::class)->resolveOpenTournament(
            $this->input('id_turnamen') ? (int) $this->input('id_turnamen') : null
        );
    }

    protected function resolveKategori(?Turnamen $turnamen): ?\App\Models\TurnamenKategori
    {
        if (! $turnamen) {
            return null;
        }

        try {
            if ($turnamen->hasMultipleKategori() && ! $this->filled('id_kategori')) {
                return null;
            }

            return $turnamen->resolveKategori(
                $this->filled('id_kategori') ? (int) $this->input('id_kategori') : null
            );
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    public function messages()
    {
        $messages = [
            'no_hp.required' => 'Nomor HP pemain 1 wajib diisi.',
            'no_hp.regex' => 'Format nomor HP pemain 1 tidak valid.',
            'nama_grup.required' => 'Nama grup wajib diisi.',
            'id_kategori.required' => 'Pilih kategori kompetisi terlebih dahulu.',
        ];

        for ($n = 2; $n <= 32; $n++) {
            $messages["no_hp_{$n}.required"] = "Nomor HP pemain {$n} wajib diisi.";
            $messages["no_hp_{$n}.regex"] = "Format nomor HP pemain {$n} tidak valid.";
            $messages["no_hp_{$n}.different"] = "Nomor HP pemain {$n} harus berbeda dari pemain lain.";
        }

        return $messages;
    }
}
