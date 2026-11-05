<?php

use App\Support\RegNo;

it('normalises reg numbers typed any way', function (string $in, string $out) {
    expect(RegNo::normalize($in))->toBe($out);
})->with([
    ['MH 12 ab 1234', 'MH12AB1234'],
    ['mh-12-ab-1234', 'MH12AB1234'],
    [' MH12AB1234 ', 'MH12AB1234'],
    ['22 BH 1234 AA', '22BH1234AA'],
]);

it('prints reg numbers with spaces and leaves odd ones alone', function () {
    expect(RegNo::pretty('MH12AB1234'))->toBe('MH 12 AB 1234')
        ->and(RegNo::pretty('MH14A0007'))->toBe('MH 14 A 0007')
        ->and(RegNo::pretty('22BH1234AA'))->toBe('22BH1234AA')
        ->and(RegNo::normalize('  -  '))->toBeNull();
});
