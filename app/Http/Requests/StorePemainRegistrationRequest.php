<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPhoneNumbers;
use App\Models\Pemain;
use App\Models\Turnamen;
use App\Services\PemainRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePemainRegistrationRequest extends FormRequest
{
    use NormalizesPhoneNumbers;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->normalizePhoneFields(['no_hp']);

        $turnamen = $this->resolveTurnamen();
        $kategori = $this->resolveKategori($turnamen);
        $maxExtra = 4;
        if ($turnamen && $turnamen->allowsGroupRegistration()) {
            $maxExtra = max(4, $kategori
                ? $kategori->friendlyPlayersPerGroup()
                : $turnamen->friendlyPlayersPerGroup());
        }

        for ($n = 2; $n <= $maxExtra; $n++) {
            $playerKey = 'player_' . $n;
            $player = (array) $this->input($playerKey, []);
            $playerPhone = $player['no_hp'] ?? null;

            if ($playerPhone && strpos((string) $playerPhone, '+') !== 0) {
                $phoneService = app(\App\Services\PhoneNumberService::class);
                $player['no_hp'] = $phoneService->normalize(
                    $phoneService->defaultCountryCode(),
                    $playerPhone
                );
                $this->merge([$playerKey => $player]);
            }
        }
    }

    public function rules()
    {
        $turnamen = $this->resolveTurnamen();
        $kategori = $this->resolveKategori($turnamen);
        $existingPlayerOne = $this->existingPlayerOne();
        $rules = array_merge(
            $this->playerFieldRules('', (bool) $existingPlayerOne),
            [
                'id_turnamen' => ['nullable', 'integer', 'exists:m_turnamen,id'],
                'id_kategori' => ['nullable', 'integer', 'exists:turnamen_kategori,id'],
                'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
                'bukti_bayar' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
            ]
        );

        if (! $turnamen) {
            $openCount = app(PemainRegistrationService::class)->getOpenTournaments()->count();
            if ($openCount > 1) {
                $rules['id_turnamen'] = ['required', 'integer', 'exists:m_turnamen,id'];
            }
        } else {
            if ($turnamen->hasMultipleKategori()) {
                $rules['id_kategori'] = [
                    'required',
                    'integer',
                    'exists:turnamen_kategori,id',
                ];
            }

            if ($turnamen->requiresPairRegistration()) {
                $rules['registration_mode'] = ['required', 'in:single,pair'];

                if ($this->input('registration_mode') === 'pair') {
                    $existingPlayerTwo = $this->existingPlayerByPrefix('player_2');
                    $rules = array_merge($rules, $this->playerFieldRules('player_2', (bool) $existingPlayerTwo));
                    $rules['foto_2'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'];
                }
            } elseif ($turnamen->allowsGroupRegistration()) {
                $rules['registration_mode'] = ['required', 'in:single,group'];

                if ($this->input('registration_mode') === 'group') {
                    $rules['nama_grup'] = ['required', 'string', 'max:255'];
                    $size = $kategori
                        ? $kategori->friendlyPlayersPerGroup()
                        : $turnamen->friendlyPlayersPerGroup();

                    for ($n = 2; $n <= $size; $n++) {
                        $prefix = 'player_' . $n;
                        $existing = $this->existingPlayerByPrefix($prefix);
                        $rules = array_merge($rules, $this->playerFieldRules($prefix, (bool) $existing));
                        $rules['foto_' . $n] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'];
                    }
                }
            } elseif ($turnamen->randomizesPartners()) {
                $rules['registration_mode'] = ['nullable', 'in:single'];
            }
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

            if ($turnamen->requiresPairRegistration() && $this->input('registration_mode') === 'pair') {
                $phone1 = trim((string) $this->input('no_hp'));
                $phone2 = trim((string) $this->input('player_2.no_hp'));

                if ($phone1 !== '' && $phone1 === $phone2) {
                    $validator->errors()->add('player_2.no_hp', 'Nomor HP pemain 2 harus berbeda dari pemain 1.');
                }
            }

            if ($turnamen->allowsGroupRegistration() && $this->input('registration_mode') === 'group') {
                $size = $kategori
                    ? $kategori->friendlyPlayersPerGroup()
                    : $turnamen->friendlyPlayersPerGroup();
                $phones = [
                    'no_hp' => trim((string) $this->input('no_hp')),
                ];

                for ($n = 2; $n <= $size; $n++) {
                    $phones['player_' . $n . '.no_hp'] = trim((string) $this->input('player_' . $n . '.no_hp'));
                }

                $seen = [];
                foreach ($phones as $field => $phone) {
                    if ($phone === '') {
                        continue;
                    }
                    if (in_array($phone, $seen, true)) {
                        $validator->errors()->add($field, 'Nomor HP harus unik untuk setiap pemain dalam grup.');
                    }
                    $seen[] = $phone;
                }

                if ($this->filled('nama_grup') && $kategori) {
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
            }
        });
    }

    public function isPairRegistration(): bool
    {
        if ($this->input('registration_mode') === 'single' || $this->input('registration_mode') === 'group') {
            return false;
        }

        if ($this->input('registration_mode') === 'pair') {
            return true;
        }

        return $this->filled('player_2.no_hp')
            || $this->filled('player_2.nama')
            || $this->filled('player_2.gender');
    }

    public function isGroupRegistration(): bool
    {
        return $this->input('registration_mode') === 'group';
    }

    public function playerOnePayload(): array
    {
        return $this->playerPayloadByPrefix('');
    }

    public function playerTwoPayload(): array
    {
        return $this->playerPayloadByPrefix('player_2');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupPlayersPayload(): array
    {
        $turnamen = $this->resolveTurnamen();
        $kategori = $this->resolveKategori($turnamen);
        $size = $kategori
            ? $kategori->friendlyPlayersPerGroup()
            : ($turnamen ? $turnamen->friendlyPlayersPerGroup() : Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP);
        $players = [$this->playerOnePayload()];

        for ($n = 2; $n <= $size; $n++) {
            $players[] = $this->playerPayloadByPrefix('player_' . $n);
        }

        return $players;
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile|null>
     */
    public function groupFotosPayload(): array
    {
        $turnamen = $this->resolveTurnamen();
        $kategori = $this->resolveKategori($turnamen);
        $size = $kategori
            ? $kategori->friendlyPlayersPerGroup()
            : ($turnamen ? $turnamen->friendlyPlayersPerGroup() : Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP);
        $fotos = [$this->file('foto')];

        for ($n = 2; $n <= $size; $n++) {
            $fotos[] = $this->file('foto_' . $n);
        }

        return $fotos;
    }

    public function existingPlayerOne(): ?Pemain
    {
        return $this->existingPlayerByPrefix('');
    }

    protected function playerPayloadByPrefix(string $prefix): array
    {
        $existing = $this->existingPlayerByPrefix($prefix);
        $input = function (string $name) use ($prefix) {
            return $prefix === ''
                ? $this->input($name)
                : $this->input($prefix . '.' . $name);
        };

        if ($existing) {
            return [
                'nama' => $existing->nama,
                'tgl_lahir' => optional($existing->tgl_lahir)->format('Y-m-d'),
                'gender' => $existing->gender,
                'no_hp' => $existing->no_hp,
                'rating' => $existing->rating,
            ];
        }

        return [
            'nama' => $input('nama'),
            'tgl_lahir' => $input('tgl_lahir'),
            'gender' => $input('gender'),
            'no_hp' => $input('no_hp'),
            'rating' => $input('rating'),
        ];
    }

    protected function existingPlayerByPrefix(string $prefix): ?Pemain
    {
        $noHp = trim((string) (
            $prefix === ''
                ? $this->input('no_hp', '')
                : $this->input($prefix . '.no_hp', '')
        ));

        if ($noHp === '') {
            return null;
        }

        return app(PemainRegistrationService::class)->findPemainByPhone($noHp);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function playerFieldRules(string $prefix, bool $existingProfile): array
    {
        $key = function (string $name) use ($prefix) {
            return $prefix === '' ? $name : $prefix . '.' . $name;
        };

        if ($existingProfile) {
            return [
                $key('no_hp') => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
                $key('nama') => ['nullable', 'string', 'max:255'],
                $key('tgl_lahir') => ['nullable', 'date', 'before:today'],
                $key('gender') => ['nullable', 'in:male,female'],
                $key('rating') => ['nullable', 'numeric', 'min:0', 'max:10'],
            ];
        }

        return [
            $key('nama') => ['required', 'string', 'max:255'],
            $key('tgl_lahir') => ['nullable', 'date', 'before:today'],
            $key('gender') => ['required', 'in:male,female'],
            $key('no_hp') => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            $key('rating') => ['nullable', 'numeric', 'min:0', 'max:10'],
        ];
    }

    protected function resolveTurnamen(): ?Turnamen
    {
        $registrationService = app(PemainRegistrationService::class);

        return $registrationService->resolveOpenTournament(
            $this->input('id_turnamen') ? (int) $this->input('id_turnamen') : null
        ) ?? $registrationService->getActiveTournament();
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

    public function resolvedKategoriId(): ?int
    {
        return optional($this->resolveKategori($this->resolveTurnamen()))->id;
    }

    public function messages()
    {
        $messages = [
            'nama.required' => 'Nama wajib diisi.',
            'tgl_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid.',
            'nama_grup.required' => 'Nama grup wajib diisi.',
            'id_kategori.required' => 'Pilih kategori kompetisi terlebih dahulu.',
            'rating.numeric' => 'Rating harus berupa angka.',
            'rating.max' => 'Rating maksimal 10.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
        ];

        for ($n = 2; $n <= 32; $n++) {
            $messages["player_{$n}.nama.required"] = "Nama pemain {$n} wajib diisi.";
            $messages["player_{$n}.tgl_lahir.before"] = "Tanggal lahir pemain {$n} harus sebelum hari ini.";
            $messages["player_{$n}.gender.required"] = "Jenis kelamin pemain {$n} wajib dipilih.";
            $messages["player_{$n}.gender.in"] = "Jenis kelamin pemain {$n} tidak valid.";
            $messages["player_{$n}.no_hp.required"] = "Nomor HP pemain {$n} wajib diisi.";
            $messages["player_{$n}.no_hp.regex"] = "Format nomor HP pemain {$n} tidak valid.";
            $messages["player_{$n}.rating.numeric"] = "Rating pemain {$n} harus berupa angka.";
            $messages["player_{$n}.rating.max"] = "Rating pemain {$n} maksimal 10.";
            $messages["foto_{$n}.image"] = "Foto pemain {$n} harus berupa gambar.";
            $messages["foto_{$n}.mimes"] = "Foto pemain {$n} harus berformat JPG, PNG, atau WebP.";
            $messages["foto_{$n}.max"] = "Ukuran foto pemain {$n} maksimal 5 MB.";
        }

        return $messages;
    }
}
