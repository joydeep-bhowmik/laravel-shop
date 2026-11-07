<?php

use App\Services\MoneyFormatter;

test('parses rupee input typed by an admin into paise', function () {
    expect(MoneyFormatter::toPaise('1,299.5'))->toBe(129950)
        ->and(MoneyFormatter::toPaise('20'))->toBe(2000);
});

test('rejects rupee input that is not a plain amount', function () {
    MoneyFormatter::toPaise('12.345');
})->throws(InvalidArgumentException::class);
