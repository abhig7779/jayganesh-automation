<?php

namespace App\Support;

/** Indian mobile numbers stored as E.164 (+919823107676). */
final class Phone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $input);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 10) {
            return '+91'.$digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '+91'.substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    public static function isValidIndianMobile(?string $e164): bool
    {
        return (bool) preg_match('/^\+91[6-9]\d{9}$/', (string) $e164);
    }

    /** +919823107676 → +91 98231 07676 */
    public static function pretty(?string $e164): string
    {
        if (! self::isValidIndianMobile($e164)) {
            return (string) $e164;
        }

        return '+91 '.substr($e164, 3, 5).' '.substr($e164, 8);
    }
}
