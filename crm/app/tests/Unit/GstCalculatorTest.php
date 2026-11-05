<?php

use App\Services\Billing\GstCalculator;

function gstCalc(bool $registered = true): GstCalculator
{
    return new GstCalculator($registered, '27');
}

function gstLine(int $rupees, int $qty = 1, float|int $rate = 18, bool $incl = true, int $discountPaise = 0, ?string $hsn = '8512'): array
{
    return ['qty' => $qty, 'unit_price_paise' => $rupees * 100, 'gst_rate' => $rate, 'price_includes_gst' => $incl, 'discount_paise' => $discountPaise, 'hsn' => $hsn];
}

/** Every rupee is accounted for: taxable + taxes = line total, and the bill adds up. */
function expectBalanced(array $r): void
{
    foreach ($r['lines'] as $l) {
        expect($l['taxable_paise'] + $l['cgst_paise'] + $l['sgst_paise'] + $l['igst_paise'])->toBe($l['line_total_paise']);
        expect($l['gross_paise'] - $l['discount_paise'])->toBe($l['line_total_paise']);
    }
    expect($r['subtotal_paise'] + $r['tax_paise'])->toBe($r['before_round_paise'])
        ->and($r['before_round_paise'] + $r['round_off_paise'])->toBe($r['total_paise'])
        ->and($r['total_paise'] % 100)->toBe(0)
        ->and(abs($r['round_off_paise']))->toBeLessThanOrEqual(50)
        ->and($r['gross_paise'] - $r['discount_paise'])->toBe($r['before_round_paise']);
}

it('splits ₹4,999 inclusive at 18% into 4,236.44 + 381.28 + 381.28', function () {
    $r = gstCalc()->calculate([gstLine(4999)]);
    $l = $r['lines'][0];

    expect($l['taxable_paise'])->toBe(423644)
        ->and($l['cgst_paise'])->toBe(38128)
        ->and($l['sgst_paise'])->toBe(38128)
        ->and($l['igst_paise'])->toBe(0)
        ->and($l['line_total_paise'])->toBe(499900)
        ->and($r['total_paise'])->toBe(499900)
        ->and($r['round_off_paise'])->toBe(0);
    expectBalanced($r);
});

it('keeps the shelf total exact for inclusive prices at every rate', function (int $rupees, float $rate) {
    $r = gstCalc()->calculate([gstLine($rupees, 3, $rate)]);
    expect($r['lines'][0]['line_total_paise'])->toBe($rupees * 300);
    expectBalanced($r);
})->with([
    [499, 5], [3200, 12], [8500, 18], [650, 28], [349, 18], [14999, 18], [1, 18], [99999, 28], [125, 2.5],
]);

it('uses IGST instead of CGST + SGST outside Maharashtra', function () {
    $r = gstCalc()->calculate([gstLine(4999)], 0, '29');
    $l = $r['lines'][0];

    expect($r['intra_state'])->toBeFalse()
        ->and($l['igst_paise'])->toBe(76256)
        ->and($l['cgst_paise'] + $l['sgst_paise'])->toBe(0)
        ->and($l['taxable_paise'])->toBe(423644);
    expectBalanced($r);
});

it('adds GST on top of exclusive prices, rounded per line to the paisa', function () {
    // ₹1,000.00 exclusive at 18%: 1,000 + 90 + 90.
    $r = gstCalc()->calculate([gstLine(1000, 1, 18, false)]);
    expect($r['lines'][0])->toMatchArray(['taxable_paise' => 100000, 'cgst_paise' => 9000, 'sgst_paise' => 9000, 'line_total_paise' => 118000]);

    // ₹333.33 × 1 at 5%: CGST 2.5% of 33,333 paise = 833.325 → 833 each.
    $r = gstCalc()->calculate([['qty' => 1, 'unit_price_paise' => 33333, 'gst_rate' => 5, 'price_includes_gst' => false]]);
    expect($r['lines'][0])->toMatchArray(['taxable_paise' => 33333, 'cgst_paise' => 833, 'sgst_paise' => 833, 'line_total_paise' => 34999])
        ->and($r['total_paise'])->toBe(35000)
        ->and($r['round_off_paise'])->toBe(1);
    expectBalanced($r);
});

it('handles mixed rates and builds the HSN summary per HSN + rate', function () {
    $r = gstCalc()->calculate([
        gstLine(4999, 2, 18, true, 0, '8512'),
        gstLine(3200, 1, 12, true, 0, '5705'),
        gstLine(650, 2, 28, true, 0, '8708'),
        gstLine(500, 1, 18, true, 0, '998714'),
        gstLine(3450, 1, 18, true, 0, '8512'),
    ]);
    expectBalanced($r);
    expect($r['total_paise'])->toBe((9998 + 3200 + 1300 + 500 + 3450) * 100);

    $hsn = collect($r['hsn_summary'])->keyBy(fn ($h) => $h['hsn'].'@'.$h['gst_rate']);
    expect($hsn)->toHaveCount(4)
        ->and($hsn['8512@18']['taxable_paise'])->toBe($r['lines'][0]['taxable_paise'] + $r['lines'][4]['taxable_paise'])
        ->and($hsn['5705@12']['tax_paise'])->toBe($r['lines'][1]['cgst_paise'] + $r['lines'][1]['sgst_paise'])
        ->and(collect($r['hsn_summary'])->sum('tax_paise'))->toBe($r['tax_paise'])
        ->and(collect($r['hsn_summary'])->sum('taxable_paise'))->toBe($r['subtotal_paise']);
});

it('charges no tax at 0% (exempt items) and still balances', function () {
    $r = gstCalc()->calculate([gstLine(1200, 2, 0, true, 0, '4901'), gstLine(4999)]);
    expect($r['lines'][0])->toMatchArray(['taxable_paise' => 240000, 'cgst_paise' => 0, 'sgst_paise' => 0, 'line_total_paise' => 240000]);
    expect(collect($r['hsn_summary'])->firstWhere('gst_rate', 0.0)['tax_paise'])->toBe(0);
    expectBalanced($r);
});

it('spreads a bill discount over lines by value so the shares add up exactly', function () {
    $r = gstCalc()->calculate([gstLine(4999), gstLine(3200, 1, 12), gstLine(500)], 100000);
    $shares = array_column($r['lines'], 'bill_discount_share_paise');

    expect(array_sum($shares))->toBe(100000)
        ->and($shares[0])->toBeGreaterThan($shares[1])->and($shares[1])->toBeGreaterThan($shares[2])
        ->and($r['discount_paise'])->toBe(100000)
        ->and($r['before_round_paise'])->toBe(869900 - 100000);
    expectBalanced($r);
});

it('taxes the discounted value, not the shelf price', function () {
    // ₹4,999 less ₹999 = ₹4,000 incl. 18% → taxable 3,389.83.
    $r = gstCalc()->calculate([gstLine(4999)], 99900);
    expect($r['lines'][0]['taxable_paise'])->toBe(338983)
        ->and($r['lines'][0]['line_total_paise'])->toBe(400000);
    expectBalanced($r);
});

it('applies a line discount and a bill discount together', function () {
    $r = gstCalc()->calculate([gstLine(1000, 2, 18, true, 20000), gstLine(500)], 5000);
    expect($r['lines'][0]['gross_paise'])->toBe(200000)
        ->and($r['discount_paise'])->toBe(20000 + 5000);
    expectBalanced($r);
});

it('applies a bill discount to exclusive-price lines', function () {
    $r = gstCalc()->calculate([gstLine(1000, 1, 18, false)], 11800); // ₹118 off ₹1,180
    expect($r['lines'][0]['taxable_paise'])->toBe(90000)
        ->and($r['lines'][0]['line_total_paise'])->toBe(106200);
    expectBalanced($r);
});

it('caps a bill discount at the bill value', function () {
    $r = gstCalc()->calculate([gstLine(500)], 999999);
    expect($r['total_paise'])->toBe(0)->and($r['discount_paise'])->toBe(50000);
    expectBalanced($r);
});

it('rounds the total to the nearest rupee as its own line', function (int $paise, int $roundOff) {
    $r = gstCalc()->calculate([['qty' => 1, 'unit_price_paise' => $paise, 'gst_rate' => 18]]);
    expect($r['round_off_paise'])->toBe($roundOff);
    expectBalanced($r);
})->with([[10049, -49], [10050, 50], [10051, 49], [10000, 0], [10099, 1]]);

it('makes a bill of supply with no tax when the shop has no GSTIN', function () {
    $r = gstCalc(false)->calculate([gstLine(4999), gstLine(650, 2, 28)], 1000);

    expect($r['registered'])->toBeFalse()
        ->and($r['tax_paise'])->toBe(0)
        ->and($r['cgst_paise'] + $r['sgst_paise'] + $r['igst_paise'])->toBe(0)
        ->and($r['subtotal_paise'])->toBe(499900 + 130000 - 1000)
        ->and(array_column($r['lines'], 'gst_rate'))->toBe([0, 0]);
    expectBalanced($r);
});

it('treats exclusive prices as final prices when not registered', function () {
    $r = gstCalc(false)->calculate([gstLine(1000, 1, 18, false)]);
    expect($r['total_paise'])->toBe(100000)->and($r['tax_paise'])->toBe(0);
});

it('rejects bad lines', function (array $l) {
    gstCalc()->calculate([$l]);
})->with([
    'zero qty' => [['qty' => 0, 'unit_price_paise' => 100, 'gst_rate' => 18]],
    'negative price' => [['qty' => 1, 'unit_price_paise' => -100, 'gst_rate' => 18]],
    'silly rate' => [['qty' => 1, 'unit_price_paise' => 100, 'gst_rate' => 180]],
])->throws(InvalidArgumentException::class);

it('allocates with largest remainders and stable ties', function () {
    expect(GstCalculator::allocate(100, [1, 1, 1]))->toBe([34, 33, 33])
        ->and(GstCalculator::allocate(10, [3, 7]))->toBe([3, 7])
        ->and(GstCalculator::allocate(0, [5, 5]))->toBe([0, 0])
        ->and(array_sum(GstCalculator::allocate(99_999, [123, 4567, 89, 1])))->toBe(99_999);
});

it('balances on many random bills', function () {
    mt_srand(42);
    for ($n = 0; $n < 300; $n++) {
        $lines = [];
        for ($k = 0, $c = mt_rand(1, 6); $k < $c; $k++) {
            $lines[] = ['qty' => mt_rand(1, 5), 'unit_price_paise' => mt_rand(1, 5_000_000), 'gst_rate' => [0, 5, 12, 18, 28][mt_rand(0, 4)], 'price_includes_gst' => (bool) mt_rand(0, 1)];
        }
        $r = gstCalc((bool) mt_rand(0, 4))->calculate($lines, mt_rand(0, 1) ? mt_rand(0, 500_000) : 0, mt_rand(0, 3) ? '27' : '24');
        expectBalanced($r);
    }
});
