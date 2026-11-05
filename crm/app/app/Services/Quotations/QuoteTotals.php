<?php

namespace App\Services\Quotations;

/**
 * GST maths for quotations, self-contained on purpose.
 *
 * MERGE NOTE (for the lead): billing builds the full App\Services\Billing\GstCalculator
 * (CGST/SGST/IGST split, rounding rules). When it is merged, make calculate() delegate to it
 * so quotes and bills always agree. Until then this does the simple, correct thing for
 * GST-inclusive shop prices: per line, taxable = round(total × 100 / (100 + rate)),
 * gst = total − taxable. Everything is integer paise.
 */
final class QuoteTotals
{
    /**
     * @param  array<int, array{qty:int, unit_price_paise:int, gst_rate:int|float|string}>  $lines  GST-inclusive unit prices
     * @return array{lines: array<int, array{line_total_paise:int, taxable_paise:int, gst_paise:int}>, taxable_paise:int, gst_paise:int, total_paise:int}
     */
    public static function calculate(array $lines): array
    {
        $out = ['lines' => [], 'taxable_paise' => 0, 'gst_paise' => 0, 'total_paise' => 0, 'mrp_total_paise' => 0, 'savings_paise' => 0];
        foreach ($lines as $i => $line) {
            $total = (int) $line['qty'] * (int) $line['unit_price_paise'];
            $taxable = self::taxableFromInclusive($total, $line['gst_rate']);
            $gst = $total - $taxable;
            $out['lines'][$i] = ['line_total_paise' => $total, 'taxable_paise' => $taxable, 'gst_paise' => $gst];
            $out['taxable_paise'] += $taxable;
            $out['gst_paise'] += $gst;
            $out['total_paise'] += $total;
            $saving = self::lineSavings((int) $line['qty'], (int) $line['unit_price_paise'], $line['mrp_paise'] ?? null);
            $out['savings_paise'] += $saving;
            $out['mrp_total_paise'] += $total + $saving;
        }

        return $out;
    }

    /**
     * "You save": (MRP − our price) × qty, only when the MRP is higher. A missing MRP, or one
     * at or below our price, saves nothing (never a negative saving).
     */
    public static function lineSavings(int $qty, int $unitPricePaise, ?int $mrpPaise): int
    {
        if (! $mrpPaise || $mrpPaise <= $unitPricePaise) {
            return 0;
        }

        return ($mrpPaise - $unitPricePaise) * $qty;
    }

    /** 1,18,000 paise at 18% → 1,00,000 taxable. Rounded half-up in integer maths (rate in basis points). */
    public static function taxableFromInclusive(int $inclusivePaise, int|float|string $rate): int
    {
        $bp = (int) round(((float) $rate) * 100);   // 18% → 1800

        return intdiv($inclusivePaise * 10000 * 2 + (10000 + $bp), (10000 + $bp) * 2);
    }

    /** A price stored ex-GST (price_includes_gst = false) turned into the GST-inclusive price. */
    public static function inclusive(int $pricePaise, int|float|string $rate, bool $alreadyInclusive): int
    {
        if ($alreadyInclusive) {
            return $pricePaise;
        }
        $bp = (int) round(((float) $rate) * 100);

        return $pricePaise + intdiv($pricePaise * $bp * 2 + 10000, 20000);
    }
}
