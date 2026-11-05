<?php

namespace App\Support;

/** 1234567 rupees → "Twelve Lakh Thirty-Four Thousand Five Hundred Sixty-Seven" (Indian system). */
final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /** 1369800 paise → "Rupees Thirteen Thousand Six Hundred Ninety-Eight Only". */
    public static function rupees(int $paise): string
    {
        $paise = abs($paise);
        $rupees = intdiv($paise, 100);
        $p = $paise % 100;
        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::number($rupees));
        if ($p > 0) {
            $words .= ' and '.self::number($p).' Paise';
        }

        return $words.' Only';
    }

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$size, $name]) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                // Above 99 crore the crore count itself is written in the Indian system.
                $parts[] = ($size === 10000000 && $count > 99 ? self::number($count) : self::belowHundred($count)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowHundred($n);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        return self::TENS[intdiv($n, 10)].($n % 10 ? '-'.self::ONES[$n % 10] : '');
    }
}
