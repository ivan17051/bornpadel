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

        $playerTwo = (array) $this->input('player_2', []);
        $playerTwoPhone = $playerTwo['no_hp'] ?? null;

        if ($playerTwoPhone && strpos((string) $playerTwoPhone, '+') !== 0) {
            $phoneService = app(\App\Services\PhoneNumberService::class);
            $playerTwo['no_hp'] = $phoneService->normalize(
                $phoneService->defaultCountryCode(),
                $playerTwoPhone
            );
            $this->merge(['player_2' => $playerTwo]);
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
        } elseif ($turnamen->isDouble()) {
            $rules['registration_mode'] = ['nullable', 'in:single,pair'];

            if ($this->input('registration_mode') === 'pair') {
                $existingPlayerTwo = $this->existingPlayerTwo();
                $rules = array_merge($rules, $this->playerFieldRules('player_2', (bool) $existingPlayerTwo));
                $rules['foto_2'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'];
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

            if ($this->input('registration_mode') === 'pair' && ! $turnamen->isDouble()) {
                $validator->errors()->add('registration_mode', 'Pendaftaran berpasangan hanya tersedia untuk turnamen double.');
            }

            if ($turnamen->isDouble() && $this->input('registration_mode') === 'pair') {
                $phone1 = trim((string) $this->input('no_hp'));
                $phone2 = trim((string) $this->input('player_2.no_hp'));

                if ($phone1 !== '' && $phone1 === $phone2) {
                    $validator->errors()->add('player_2.no_hp', 'Nomor HP pemain 2 harus berbeda dari pemain 1.');
                }
            }
        });
    }

    public function isPairRegistration(): bool
    {
        if ($this->input('registration_mode') === 'single') {
            return false;
        }

        if ($this->input('registration_mode') === 'pair') {
            return true;
        }

        return $this->filled('player_2.no_hp')
            || $this->filled('player_2.nama')
            || $this->filled('player_2.gender');
    }

    public function playerOnePayload(): array
    {
        $existing = $this->existingPlayerOne();

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
            'nama' => $this->input('nama'),
            'tgl_lahir' => $this->input('tgl_lahir'),
            'gender' => $this->input('gender'),
            'no_hp' => $this->input('no_hp'),
            'rating' => $this->input('rating'),
        ];
    }

    public function playerTwoPayload(): array
    {
        $existing = $this->existingPlayerTwo();

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
            'nama' => $this->input('player_2.nama'),
            'tgl_lahir' => $this->input('player_2.tgl_lahir'),
            'gender' => $this->input('player_2.gender'),
            'no_hp' => $this->input('player_2.no_hp'),
            'rating' => $this->input('player_2.rating'),
        ];
    }

    public function existingPlayerOne(): ?Pemain
    {
        $noHp = trim((string) $this->input('no_hp', ''));

        if ($noHp === '') {
            return null;
        }

        return app(PemainRegistrationService::class)->findPemainByPhone($noHp);
    }

    public function existingPlayerTwo(): ?Pemain
    {
        $noHp = trim((string) $this->input('player_2.no_hp', ''));

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
            'tgl_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'player_2.tgl_lahir.before' => 'Tanggal lahir pemain 2 harus sebelum hari ini.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'player_2.gender.required' => 'Jenis kelamin pemain 2 wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'player_2.gender.in' => 'Jenis kelamin pemain 2 tidak valid.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'player_2.no_hp.required' => 'Nomor HP pemain 2 wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid.',
            'player_2.no_hp.regex' => 'Format nomor HP pemain 2 tidak valid.',
            'rating.numeric' => 'Rating harus berupa angka.',
            'player_2.rating.numeric' => 'Rating pemain 2 harus berupa angka.',
            'rating.max' => 'Rating maksimal 10.',
            'player_2.rating.max' => 'Rating pemain 2 maksimal 10.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto_2.image' => 'Foto pemain 2 harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto_2.mimes' => 'Foto pemain 2 harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
            'foto_2.max' => 'Ukuran foto pemain 2 maksimal 5 MB.',
        ];
    }
}
