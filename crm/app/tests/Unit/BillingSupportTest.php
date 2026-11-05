<?php

use App\Services\Billing\InvoiceService;
use App\Services\Billing\NumberSeries;
use App\Services\Billing\PaymentService;
use App\Support\AmountInWords;
use App\Support\SearchText;
use Carbon\Carbon;

it('writes amounts in words the Indian way (lakh, crore)', function (int $paise, string $words) {
    expect(AmountInWords::rupees($paise))->toBe($words);
})->with([
    [1369800, 'Rupees Thirteen Thousand Six Hundred Ninety-Eight Only'],
    [499900, 'Rupees Four Thousand Nine Hundred Ninety-Nine Only'],
    [12345678900, 'Rupees Twelve Crore Thirty-Four Lakh Fifty-Six Thousand Seven Hundred Eighty-Nine Only'],
    [10000000, 'Rupees One Lakh Only'],
    [65000050, 'Rupees Six Lakh Fifty Thousand and Fifty Paise Only'],
    [0, 'Rupees Zero Only'],
    [1100, 'Rupees Eleven Only'],
]);

it('works out the Indian financial year (April to March)', function (string $date, string $fy) {
    expect(NumberSeries::financialYear(Carbon::parse($date)))->toBe($fy);
})->with([
    ['2026-11-05', '2627'], ['2026-04-01', '2627'], ['2026-03-31', '2526'], ['2025-12-31', '2526'], ['2099-06-01', '9900'],
]);

it('normalises search text like the browser does', function (string $in, array $tokens) {
    expect(SearchText::tokens($in))->toBe($tokens);
})->with([
    ['7D floor mats', ['7d', 'floor', 'mats']],
    ['7 d mats', ['7d', 'mats']],
    ['H-4 bulb', ['h4', 'bulb']],
    ['h 4', ['h4']],
    ['L.E.D. Gleam 2.0', ['led', 'gleam', '20']],
    ['Scorpio-N seat cover', ['scorpion', 'seat', 'cover']],
    ['  DHC/Gleam (H7)  ', ['dhc', 'gleam', 'h7']],
    ['Android stereo 9" 2+32 GB', ['android', 'stereo', '9', '2', '32', 'gb']],
]);

it('compares SKUs and barcodes on letters and digits only', function () {
    expect(SearchText::code('DHC-GLM-H4'))->toBe('dhcglmh4')
        ->and(SearchText::code(' 8901234 567890 '))->toBe('8901234567890');
});

it('works out MRP savings per line and never below zero', function () {
    expect(InvoiceService::mrpSaving(650000, 2, 999800))->toBe(300200)
        ->and(InvoiceService::mrpSaving(null, 2, 999800))->toBe(0)
        ->and(InvoiceService::mrpSaving(400000, 1, 499900))->toBe(0);
});

it('applies a payment to the oldest bills first', function () {
    $bills = [(object) ['id' => 1, 'due_paise' => 150000], (object) ['id' => 2, 'due_paise' => 200000], (object) ['id' => 3, 'due_paise' => 50000]];
    expect(PaymentService::allocateOldestFirst(250000, $bills))->toBe([1 => 150000, 2 => 100000])
        ->and(PaymentService::allocateOldestFirst(400000, $bills))->toBe([1 => 150000, 2 => 200000, 3 => 50000])
        ->and(PaymentService::allocateOldestFirst(0, $bills))->toBe([]);
});

it('sets the bill status from paid and due', function () {
    expect(InvoiceService::statusFor(100, 0))->toBe('paid')
        ->and(InvoiceService::statusFor(100, 50))->toBe('partial')
        ->and(InvoiceService::statusFor(0, 50))->toBe('due');
});
