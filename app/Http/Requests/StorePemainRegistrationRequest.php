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

        foreach (['player_2', 'player_3', 'player_4'] as $playerKey) {
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
        $existingPlayerOne = $this->existingPlayerOne();
        $rules = array_merge(
            $this->playerFieldRules('', (bool) $existingPlayerOne),
            [
                'id_turnamen' => ['nullable', 'integer', 'exists:m_turnamen,id'],
                'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
                'bukti_bayar' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
            ]
        );

        if (! $turnamen) {
            $openCount = app(PemainRegistrationService::class)->getOpenTournaments()->count();
            if ($openCount > 1) {
                $rules['id_turnamen'] = ['required', 'integer', 'exists:m_turnamen,id'];
            }
        } elseif ($turnamen->requiresPairRegistration()) {
            $rules['registration_mode'] = ['required', 'in:single,pair'];

            if ($this->input('registration_mode') === 'pair') {
                $existingPlayerTwo = $this->existingPlayerTwo();
                $rules = array_merge($rules, $this->playerFieldRules('player_2', (bool) $existingPlayerTwo));
                $rules['foto_2'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'];
            }
        } elseif ($turnamen->allowsGroupRegistration()) {
            $rules['registration_mode'] = ['required', 'in:single,group'];

            if ($this->input('registration_mode') === 'group') {
                $rules['nama_grup'] = ['required', 'string', 'max:255'];
                foreach ([2, 3, 4] as $n) {
                    $prefix = 'player_' . $n;
                    $existing = $this->existingPlayerByPrefix($prefix);
                    $rules = array_merge($rules, $this->playerFieldRules($prefix, (bool) $existing));
                    $rules['foto_' . $n] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'];
                }
            }
        } elseif ($turnamen->randomizesPartners()) {
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
                $phones = [
                    'no_hp' => trim((string) $this->input('no_hp')),
                    'player_2.no_hp' => trim((string) $this->input('player_2.no_hp')),
                    'player_3.no_hp' => trim((string) $this->input('player_3.no_hp')),
                    'player_4.no_hp' => trim((string) $this->input('player_4.no_hp')),
                ];

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

                if ($this->filled('nama_grup')) {
                    try {
                        app(PemainRegistrationService::class)->assertGroupNameAvailable(
                            $turnamen,
                            (string) $this->input('nama_grup')
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

    public function playerThreePayload(): array
    {
        return $this->playerPayloadByPrefix('player_3');
    }

    public function playerFourPayload(): array
    {
        return $this->playerPayloadByPrefix('player_4');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupPlayersPayload(): array
    {
        return [
            $this->playerOnePayload(),
            $this->playerTwoPayload(),
            $this->playerThreePayload(),
            $this->playerFourPayload(),
        ];
    }

    public function existingPlayerOne(): ?Pemain
    {
        return $this->existingPlayerByPrefix('');
    }

    public function existingPlayerTwo(): ?Pemain
    {
        return $this->existingPlayerByPrefix('player_2');
    }

    public function existingPlayerThree(): ?Pemain
    {
        return $this->existingPlayerByPrefix('player_3');
    }

    public function existingPlayerFour(): ?Pemain
    {
        return $this->existingPlayerByPrefix('player_4');
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

    public function messages()
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'player_2.nama.required' => 'Nama pemain 2 wajib diisi.',
            'player_3.nama.required' => 'Nama pemain 3 wajib diisi.',
            'player_4.nama.required' => 'Nama pemain 4 wajib diisi.',
            'tgl_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'player_2.tgl_lahir.before' => 'Tanggal lahir pemain 2 harus sebelum hari ini.',
            'player_3.tgl_lahir.before' => 'Tanggal lahir pemain 3 harus sebelum hari ini.',
            'player_4.tgl_lahir.before' => 'Tanggal lahir pemain 4 harus sebelum hari ini.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'player_2.gender.required' => 'Jenis kelamin pemain 2 wajib dipilih.',
            'player_3.gender.required' => 'Jenis kelamin pemain 3 wajib dipilih.',
            'player_4.gender.required' => 'Jenis kelamin pemain 4 wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'player_2.gender.in' => 'Jenis kelamin pemain 2 tidak valid.',
            'player_3.gender.in' => 'Jenis kelamin pemain 3 tidak valid.',
            'player_4.gender.in' => 'Jenis kelamin pemain 4 tidak valid.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'player_2.no_hp.required' => 'Nomor HP pemain 2 wajib diisi.',
            'player_3.no_hp.required' => 'Nomor HP pemain 3 wajib diisi.',
            'player_4.no_hp.required' => 'Nomor HP pemain 4 wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid.',
            'player_2.no_hp.regex' => 'Format nomor HP pemain 2 tidak valid.',
            'player_3.no_hp.regex' => 'Format nomor HP pemain 3 tidak valid.',
            'player_4.no_hp.regex' => 'Format nomor HP pemain 4 tidak valid.',
            'nama_grup.required' => 'Nama grup wajib diisi.',
            'rating.numeric' => 'Rating harus berupa angka.',
            'player_2.rating.numeric' => 'Rating pemain 2 harus berupa angka.',
            'player_3.rating.numeric' => 'Rating pemain 3 harus berupa angka.',
            'player_4.rating.numeric' => 'Rating pemain 4 harus berupa angka.',
            'rating.max' => 'Rating maksimal 10.',
            'player_2.rating.max' => 'Rating pemain 2 maksimal 10.',
            'player_3.rating.max' => 'Rating pemain 3 maksimal 10.',
            'player_4.rating.max' => 'Rating pemain 4 maksimal 10.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto_2.image' => 'Foto pemain 2 harus berupa gambar.',
            'foto_3.image' => 'Foto pemain 3 harus berupa gambar.',
            'foto_4.image' => 'Foto pemain 4 harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto_2.mimes' => 'Foto pemain 2 harus berformat JPG, PNG, atau WebP.',
            'foto_3.mimes' => 'Foto pemain 3 harus berformat JPG, PNG, atau WebP.',
            'foto_4.mimes' => 'Foto pemain 4 harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
            'foto_2.max' => 'Ukuran foto pemain 2 maksimal 5 MB.',
            'foto_3.max' => 'Ukuran foto pemain 3 maksimal 5 MB.',
            'foto_4.max' => 'Ukuran foto pemain 4 maksimal 5 MB.',
        ];
    }
}
