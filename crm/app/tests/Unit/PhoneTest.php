<?php

use App\Support\Phone;

it('normalises Indian mobile numbers to E.164', function (string $in, string $out) {
    expect(Phone::normalize($in))->toBe($out);
})->with([
    ['9823107676', '+919823107676'],
    ['98231 07676', '+919823107676'],
    ['+91 98231-07676', '+919823107676'],
    ['09823107676', '+919823107676'],
    ['919823107676', '+919823107676'],
]);

it('formats and validates numbers', function () {
    expect(Phone::pretty('+919823107676'))->toBe('+91 98231 07676')
        ->and(Phone::isValidIndianMobile('+919823107676'))->toBeTrue()
        ->and(Phone::isValidIndianMobile('+911234567890'))->toBeFalse();
});
