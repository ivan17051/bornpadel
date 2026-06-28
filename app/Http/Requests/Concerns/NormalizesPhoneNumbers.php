<?php

namespace App\Http\Requests\Concerns;

use App\Services\PhoneNumberService;

trait NormalizesPhoneNumbers
{
    protected function normalizePhoneFields(array $fields = ['no_hp', 'partner_no_hp']): void
    {
        $phoneService = app(PhoneNumberService::class);
        $merge = [];

        foreach ($fields as $field) {
            $localField = $field . '_local';
            $countryField = $field . '_country';

            if ($this->filled($localField) || $this->filled($countryField)) {
                $merge[$field] = $phoneService->normalize(
                    $this->input($countryField),
                    $this->input($localField)
                );
            } elseif ($this->filled($field) && strpos((string) $this->input($field), '+') !== 0) {
                $merge[$field] = $phoneService->normalize($phoneService->defaultCountryCode(), $this->input($field));
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
