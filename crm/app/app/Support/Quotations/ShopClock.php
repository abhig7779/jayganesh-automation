<?php

namespace App\Support\Quotations;

use Illuminate\Support\Carbon;

/**
 * "Today" at the shop. The app runs in UTC, but a follow-up due "today" means today in Pune,
 * so a 1 a.m. IST check doesn't still show yesterday's list.
 */
final class ShopClock
{
    public const TZ = 'Asia/Kolkata';

    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    /** Today's date in Pune as Y-m-d. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    public static function todayDate(): Carbon
    {
        return self::now()->startOfDay();
    }

    /** Indian financial year code: Nov 2026 → "2627", Feb 2027 → "2627", Apr 2027 → "2728". */
    public static function financialYear(?Carbon $at = null): string
    {
        $at ??= self::now();
        $start = $at->month >= 4 ? $at->year : $at->year - 1;

        return substr((string) $start, -2).substr((string) ($start + 1), -2);
    }
}
