<?php

namespace App\Services;

class MetaIdentifierNormalizer
{
    public function normalizeEmail(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
    }

    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (!$digits) {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '91' . substr($digits, 1);
        }

        if (strlen($digits) < 10) {
            return null;
        }

        return $digits;
    }

    public function hash(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash('sha256', $value);
    }
}
