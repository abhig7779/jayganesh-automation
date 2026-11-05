<?php

use App\Support\Money;

it('formats paise the Indian way', function (int $paise, string $out) {
    expect(Money::format($paise))->toBe($out);
})->with([
    [0, '₹0'],
    [49900, '₹499'],
    [499900, '₹4,999'],
    [12345600, '₹1,23,456'],
    [1234567890, '₹1,23,45,678.90'],
    [-150000, '-₹1,500'],
]);

it('parses typed amounts into paise', function ($in, int $out) {
    expect(Money::toPaise($in))->toBe($out);
})->with([
    ['1,499.50', 149950],
    ['₹4999', 499900],
    ['0.5', 50],
    [1200, 120000],
    ['', 0],
]);

it('rejects junk amounts', function () {
    Money::toPaise('12abc');
})->throws(InvalidArgumentException::class);

it('takes percentages without float drift', function () {
    expect(Money::percent(100000, 18))->toBe(18000)
        ->and(Money::percent(99, 18))->toBe(18)      // 17.82 → 18
        ->and(Money::percent(423729, 18))->toBe(76271)  // 76271.22 → 76271
        ->and(Money::percent(1000, 2.5))->toBe(25);
});
