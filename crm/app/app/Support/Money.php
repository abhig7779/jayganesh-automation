<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is always an integer count of paise (ARCHITECTURE.md §4). Never use floats for money.
 * Money::format(12345600) → "₹1,23,456"; with decimals → "₹1,23,456.00".
 */
final class Money
{
    /** "1,499.50" / "₹1499.5" / 1499 → 149950 paise. */
    public static function toPaise(string|int|float|null $rupees): int
    {
        if ($rupees === null || $rupees === '') {
            return 0;
        }
        if (is_int($rupees)) {
            return $rupees * 100;
        }
        $clean = str_replace([',', '₹', ' '], '', (string) $rupees);
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $clean)) {
            throw new InvalidArgumentException("Not a money amount: {$rupees}");
        }
        $negative = str_starts_with($clean, '-');
        $clean = ltrim($clean, '-');
        [$whole, $fraction] = array_pad(explode('.', $clean, 2), 2, '0');
        $paise = (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$paise : $paise;
    }

    /** Indian grouping: 12345678 paise → "₹1,23,456.78" (decimals hidden when zero unless forced). */
    public static function format(int $paise, bool $forceDecimals = false, bool $symbol = true): string
    {
        $negative = $paise < 0;
        $paise = abs($paise);
        $rupees = intdiv($paise, 100);
        $fraction = $paise % 100;

        $s = (string) $rupees;
        if (strlen($s) > 3) {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $s = $rest.','.$last3;
        }
        if ($forceDecimals || $fraction > 0) {
            $s .= '.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
        }

        return ($negative ? '-' : '').($symbol ? '₹' : '').$s;
    }

    /** Percentage of an amount, rounded half-up to the paisa. rate is e.g. 18 or 2.5. */
    public static function percent(int $paise, float|int|string $rate): int
    {
        // Work in basis points to avoid float error: 18% = 1800 bp.
        $bp = (int) round(((float) $rate) * 100);

        return intdiv($paise * $bp * 2 + 10000, 20000) - ($paise * $bp < 0 ? 1 : 0);
    }
}
