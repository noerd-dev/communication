<?php

namespace Noerd\Communication\Support;

/**
 * Normalises a phone number as people type it into E.164 (`+4917112345678`).
 */
final class PhoneNumber
{
    public static function normalize(?string $raw, string $defaultCountryCode = '49'): ?string
    {
        $value = preg_replace('/[\s\-\/().]/', '', (string) $raw) ?? '';

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '+')) {
            $digits = mb_substr($value, 1);
        } elseif (str_starts_with($value, '00')) {
            $digits = mb_substr($value, 2);
        } elseif (str_starts_with($value, '0')) {
            $digits = $defaultCountryCode . mb_substr($value, 1);
        } else {
            $digits = $value;
        }

        if (! preg_match('/^[1-9]\d{6,14}$/', $digits)) {
            return null;
        }

        return '+' . $digits;
    }
}
