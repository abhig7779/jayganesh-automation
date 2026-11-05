<?php

namespace App\Support;

/** Vehicle registration numbers stored as MH12AB1234 (uppercase, no spaces or dashes). */
final class RegNo
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper($input));

        return $clean === '' ? null : $clean;
    }

    /** MH12AB1234 → MH 12 AB 1234. Anything else (BH series, old plates) is returned as stored. */
    public static function pretty(?string $regNo): ?string
    {
        if ($regNo === null) {
            return null;
        }
        if (preg_match('/^([A-Z]{2})(\d{1,2})([A-Z]{0,3})(\d{1,4})$/', $regNo, $m)) {
            return trim(implode(' ', array_filter([$m[1], $m[2], $m[3], $m[4]], fn ($p) => $p !== '')));
        }

        return $regNo;
    }
}
