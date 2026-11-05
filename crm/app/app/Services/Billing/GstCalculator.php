<?php

namespace App\Services\Billing;

use App\Models\Setting;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Bill maths (ARCHITECTURE.md §4). Integer paise only, no floats on money.
 *
 * - Prices can be GST-inclusive (the shop's usual shelf price) or exclusive, per line.
 * - Each line is rounded to the paisa. Inclusive lines keep their shelf total exactly
 *   (₹4,999 stays ₹4,999: taxable 4,236.44 + CGST 381.28 + SGST 381.28).
 * - CGST + SGST when the place of supply is the shop's state, otherwise IGST.
 * - A bill-level discount (an amount off the payable total) is spread over the lines in
 *   proportion to their value, largest-remainder, so the shares add up to the paisa.
 * - The total is rounded to the nearest rupee and the difference is its own round-off line.
 * - Not GST-registered (empty GSTIN): a "Bill of supply" with no tax lines at all.
 *
 * The same rules are mirrored in resources/js/modules/bill-totals.js for the live preview;
 * the server result is the one that is saved.
 */
final class GstCalculator
{
    public function __construct(
        private readonly bool $registered = true,
        private readonly string $shopStateCode = '27',
    ) {}

    public static function fromSettings(?int $branchId = null): self
    {
        return new self(
            filled(Setting::get('tax.gstin', null, $branchId)),
            (string) (Setting::get('tax.state_code', null, $branchId) ?: '27'),
        );
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    public function shopStateCode(): string
    {
        return $this->shopStateCode;
    }

    /**
     * @param  array<int, array{qty:int, unit_price_paise:int, gst_rate?:int|float|string, price_includes_gst?:bool, discount_paise?:int, hsn?:?string}>  $lines
     * @return array{registered:bool, intra_state:bool, place_of_supply:string, lines:array<int,array>, gross_paise:int, discount_paise:int, subtotal_paise:int, cgst_paise:int, sgst_paise:int, igst_paise:int, tax_paise:int, before_round_paise:int, round_off_paise:int, total_paise:int, hsn_summary:array<int,array>}
     */
    public function calculate(array $lines, int $billDiscountPaise = 0, ?string $placeOfSupply = null): array
    {
        if ($billDiscountPaise < 0) {
            throw new InvalidArgumentException('Discount cannot be negative.');
        }
        $pos = $placeOfSupply ?: $this->shopStateCode;
        $intra = $pos === $this->shopStateCode;

        // 1. Each line's value after its own discount, in GST-inclusive terms.
        $prepared = [];
        foreach (array_values($lines) as $i => $line) {
            $qty = (int) ($line['qty'] ?? 0);
            $unit = (int) ($line['unit_price_paise'] ?? 0);
            if ($qty < 1 || $unit < 0) {
                throw new InvalidArgumentException("Line {$i}: quantity must be at least 1 and the price cannot be negative.");
            }
            $rateBp = $this->registered ? self::basisPoints($line['gst_rate'] ?? 0) : 0;
            $inclusive = (bool) ($line['price_includes_gst'] ?? true);
            $basisGross = $unit * $qty;
            $lineDiscount = min(max(0, (int) ($line['discount_paise'] ?? 0)), $basisGross);
            $basisNet = $basisGross - $lineDiscount;

            $prepared[] = $line + [
                '_rate_bp' => $rateBp,
                '_inclusive' => $inclusive || $rateBp === 0,
                '_basis_net' => $basisNet,
                '_gross_incl' => $inclusive ? $basisGross : $basisGross + $this->exclusiveTax($basisGross, $rateBp, $intra),
                '_net_incl' => $inclusive ? $basisNet : $basisNet + $this->exclusiveTax($basisNet, $rateBp, $intra),
            ];
        }

        // 2. Spread the bill discount over the lines by value.
        $shares = self::allocate(min($billDiscountPaise, array_sum(array_column($prepared, '_net_incl'))), array_column($prepared, '_net_incl'));

        // 3. Tax per line, rounded to the paisa.
        $out = [];
        foreach ($prepared as $i => $p) {
            $share = $shares[$i] ?? 0;
            $bp = $p['_rate_bp'];

            if ($p['_inclusive']) {
                $lineTotal = $p['_net_incl'] - $share;
                $taxable = self::div($lineTotal * 10000, 10000 + $bp);
                $tax = $lineTotal - $taxable;
                [$cgst, $sgst, $igst] = $intra ? [intdiv($tax + 1, 2), intdiv($tax, 2), 0] : [0, 0, $tax];
            } else {
                $taxable = $p['_basis_net'] - ($share > 0 ? self::div($share * 10000, 10000 + $bp) : 0);
                [$cgst, $sgst, $igst] = $intra
                    ? [Money::percent($taxable, $bp / 200), Money::percent($taxable, $bp / 200), 0]
                    : [0, 0, Money::percent($taxable, $bp / 100)];
                $lineTotal = $taxable + $cgst + $sgst + $igst;
            }

            $clean = array_filter($p, fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
            $out[] = array_merge($clean, [
                'gst_rate' => $bp / 100,
                'gross_paise' => $p['_gross_incl'],
                'discount_paise' => $p['_gross_incl'] - $lineTotal,
                'bill_discount_share_paise' => $share,
                'taxable_paise' => $taxable,
                'cgst_paise' => $cgst,
                'sgst_paise' => $sgst,
                'igst_paise' => $igst,
                'line_total_paise' => $lineTotal,
            ]);
        }

        $before = array_sum(array_column($out, 'line_total_paise'));
        $total = intdiv($before + 50, 100) * 100;
        $sum = fn (string $k) => array_sum(array_column($out, $k));

        return [
            'registered' => $this->registered,
            'intra_state' => $intra,
            'place_of_supply' => $pos,
            'lines' => $out,
            'gross_paise' => $sum('gross_paise'),
            'discount_paise' => $sum('discount_paise'),
            'subtotal_paise' => $sum('taxable_paise'),
            'cgst_paise' => $sum('cgst_paise'),
            'sgst_paise' => $sum('sgst_paise'),
            'igst_paise' => $sum('igst_paise'),
            'tax_paise' => $sum('cgst_paise') + $sum('sgst_paise') + $sum('igst_paise'),
            'before_round_paise' => $before,
            'round_off_paise' => $total - $before,
            'total_paise' => $total,
            'hsn_summary' => self::hsnSummary($out),
        ];
    }

    /** Tax rows grouped by HSN + rate, as printed under a GST invoice. */
    public static function hsnSummary(iterable $lines): array
    {
        $rows = [];
        foreach ($lines as $l) {
            $l = is_array($l) ? $l : $l->toArray();
            $rate = (float) ($l['gst_rate'] ?? 0);
            $key = ($l['hsn'] ?? '').'|'.$rate;
            $rows[$key] ??= ['hsn' => $l['hsn'] ?? null, 'gst_rate' => $rate, 'taxable_paise' => 0, 'cgst_paise' => 0, 'sgst_paise' => 0, 'igst_paise' => 0, 'tax_paise' => 0];
            foreach (['taxable_paise', 'cgst_paise', 'sgst_paise', 'igst_paise'] as $k) {
                $rows[$key][$k] += (int) ($l[$k] ?? 0);
            }
            $rows[$key]['tax_paise'] = $rows[$key]['cgst_paise'] + $rows[$key]['sgst_paise'] + $rows[$key]['igst_paise'];
        }
        uasort($rows, fn ($a, $b) => [$a['gst_rate'], (string) $a['hsn']] <=> [$b['gst_rate'], (string) $b['hsn']]);

        return array_values($rows);
    }

    /**
     * Split $amount over $weights in proportion, largest remainder first, so the parts add up
     * exactly. Ties go to the earlier line.
     *
     * @param  int[]  $weights
     * @return int[]
     */
    public static function allocate(int $amount, array $weights): array
    {
        $weights = array_values($weights);
        $total = array_sum($weights);
        if ($amount <= 0 || $total <= 0) {
            return array_fill(0, count($weights), 0);
        }
        $parts = [];
        $remainders = [];
        foreach ($weights as $i => $w) {
            $parts[$i] = intdiv($amount * $w, $total);
            $remainders[$i] = ($amount * $w) % $total;
        }
        $left = $amount - array_sum($parts);
        arsort($remainders, SORT_NUMERIC);
        // arsort is not stable on ties in every PHP build; sort by remainder desc, then index asc.
        $order = array_keys($remainders);
        usort($order, fn ($a, $b) => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        foreach ($order as $i) {
            if ($left-- <= 0) {
                break;
            }
            $parts[$i]++;
        }
        ksort($parts);

        return $parts;
    }

    private function exclusiveTax(int $taxable, int $bp, bool $intra): int
    {
        return $intra ? 2 * Money::percent($taxable, $bp / 200) : Money::percent($taxable, $bp / 100);
    }

    /** Integer division rounded half-up (both operands non-negative). */
    private static function div(int $a, int $b): int
    {
        return intdiv(2 * $a + $b, 2 * $b);
    }

    private static function basisPoints(int|float|string $rate): int
    {
        $bp = (int) round(((float) $rate) * 100);
        if ($bp < 0 || $bp > 10000) {
            throw new InvalidArgumentException("GST rate out of range: {$rate}");
        }

        return $bp;
    }
}
