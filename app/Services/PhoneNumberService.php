<?php

namespace App\Services;

class PhoneNumberService
{
    public function countries(): array
    {
        return config('phone_countries', []);
    }

    public function defaultCountryCode(): string
    {
        foreach ($this->countries() as $country) {
            if (! empty($country['default'])) {
                return $country['code'];
            }
        }

        return '+62';
    }

    public function normalize(?string $countryCode, ?string $localNumber): string
    {
        $code = $this->sanitizeCountryCode($countryCode ?: $this->defaultCountryCode());
        $local = $this->sanitizeLocalNumber($localNumber ?? '');

        if ($local === '') {
            return '';
        }

        if (strpos($local, '+') === 0) {
            return $local;
        }

        return $code . $local;
    }

    public function parse(?string $fullNumber): array
    {
        $full = trim((string) $fullNumber);

        if ($full === '') {
            return [
                'country_code' => $this->defaultCountryCode(),
                'local_number' => '',
                'full' => '',
            ];
        }

        if (strpos($full, '+') !== 0) {
            $digits = preg_replace('/\D+/', '', $full) ?? '';

            return [
                'country_code' => $this->defaultCountryCode(),
                'local_number' => $digits,
                'full' => $this->normalize($this->defaultCountryCode(), $digits),
            ];
        }

        $codes = collect($this->countries())
            ->pluck('code')
            ->sortByDesc(fn (string $code) => strlen($code))
            ->values();

        foreach ($codes as $code) {
            if (strpos($full, $code) === 0) {
                $local = substr($full, strlen($code));

                return [
                    'country_code' => $code,
                    'local_number' => $this->sanitizeLocalNumber($local),
                    'full' => $code . $this->sanitizeLocalNumber($local),
                ];
            }
        }

        return [
            'country_code' => $this->defaultCountryCode(),
            'local_number' => ltrim($full, '+'),
            'full' => $full,
        ];
    }

    public function sanitizeCountryCode(string $code): string
    {
        $normalized = '+' . preg_replace('/\D+/', '', $code);

        return $normalized === '+' ? $this->defaultCountryCode() : $normalized;
    }

    public function sanitizeLocalNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strpos($digits, '0') === 0) {
            $digits = ltrim($digits, '0');
        }

        return $digits;
    }
}
