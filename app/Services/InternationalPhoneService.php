<?php

namespace App\Services;

class InternationalPhoneService
{
    public const DEFAULT_COUNTRY = 'IN';

    /** Common quick-select countries. Any other country remains supported through full +E.164 input. */
    private const COUNTRIES = [
        'IN' => ['name' => 'India', 'code' => '91'],
        'AE' => ['name' => 'United Arab Emirates', 'code' => '971'],
        'SA' => ['name' => 'Saudi Arabia', 'code' => '966'],
        'QA' => ['name' => 'Qatar', 'code' => '974'],
        'KW' => ['name' => 'Kuwait', 'code' => '965'],
        'OM' => ['name' => 'Oman', 'code' => '968'],
        'BH' => ['name' => 'Bahrain', 'code' => '973'],
        'GB' => ['name' => 'United Kingdom', 'code' => '44'],
        'US' => ['name' => 'United States', 'code' => '1'],
        'CA' => ['name' => 'Canada', 'code' => '1'],
        'AU' => ['name' => 'Australia', 'code' => '61'],
        'SG' => ['name' => 'Singapore', 'code' => '65'],
        'MY' => ['name' => 'Malaysia', 'code' => '60'],
        'DE' => ['name' => 'Germany', 'code' => '49'],
        'FR' => ['name' => 'France', 'code' => '33'],
    ];

    public function parse(?string $phone, ?string $countryIso = null): ?array
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $hasInternationalPrefix = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $hasInternationalPrefix = true;
        }

        $countryIso = strtoupper(trim((string) $countryIso));
        if (!$hasInternationalPrefix) {
            if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }

            if ($countryIso !== '' && isset(self::COUNTRIES[$countryIso])) {
                $countryCode = self::COUNTRIES[$countryIso]['code'];
                $nationalNumber = ltrim($digits, '0');
                $digits = str_starts_with($nationalNumber, $countryCode)
                    ? $nationalNumber
                    : $countryCode . $nationalNumber;
            } elseif ($this->isIndianNationalNumber($digits)) {
                $digits = '91' . $digits;
                $countryIso = self::DEFAULT_COUNTRY;
            } elseif (strlen($digits) === 12 && str_starts_with($digits, '91') && $this->isIndianNationalNumber(substr($digits, 2))) {
                $countryIso = self::DEFAULT_COUNTRY;
            } else {
                return null;
            }
        }

        if (!preg_match('/^[1-9][0-9]{7,14}$/', $digits) || preg_match('/^(\d)\1+$/', $digits)) {
            return null;
        }

        $countryIso = $this->resolveCountryIso($digits, $countryIso);
        $countryCode = $this->resolveCountryCode($digits, $countryIso);

        if ($countryIso === self::DEFAULT_COUNTRY && !$this->isIndianNationalNumber(substr($digits, 2))) {
            return null;
        }

        return [
            'e164' => '+' . $digits,
            'normalized' => $digits,
            'country_iso' => $countryIso ?: null,
            'country_code' => $countryCode,
        ];
    }

    public function countryOptions(): array
    {
        return self::COUNTRIES;
    }

    public function displayLocal(?string $phone, ?string $countryIso = null): string
    {
        $parsed = $this->parse($phone, $countryIso);
        if (!$parsed) {
            return trim((string) $phone);
        }

        if (($parsed['country_iso'] ?? null) === self::DEFAULT_COUNTRY) {
            return substr($parsed['normalized'], -10);
        }

        return $parsed['e164'];
    }

    private function isIndianNationalNumber(string $digits): bool
    {
        return (bool) preg_match('/^[6-9][0-9]{9}$/', $digits)
            && !preg_match('/^(\d)\1{9}$/', $digits);
    }

    private function resolveCountryIso(string $digits, string $requestedCountry): ?string
    {
        if ($requestedCountry !== '' && isset(self::COUNTRIES[$requestedCountry])) {
            return $requestedCountry;
        }

        foreach (self::COUNTRIES as $iso => $country) {
            if (str_starts_with($digits, $country['code'])) {
                return $iso;
            }
        }

        return null;
    }

    private function resolveCountryCode(string $digits, ?string $countryIso): ?string
    {
        if ($countryIso && isset(self::COUNTRIES[$countryIso])) {
            return self::COUNTRIES[$countryIso]['code'];
        }

        return null;
    }
}
