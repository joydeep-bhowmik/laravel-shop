<?php

use App\Services\MoneyFormatter;

test('formats paise as rupees with two decimals', function () {
    expect(MoneyFormatter::format(0))->toBe('₹0.00')
        ->and(MoneyFormatter::format(5))->toBe('₹0.05')
        ->and(MoneyFormatter::format(49900))->toBe('₹499.00');
});

test('groups thousands using the Indian numbering system', function () {
    expect(MoneyFormatter::format(100000))->toBe('₹1,000.00')
        ->and(MoneyFormatter::format(12345600))->toBe('₹1,23,456.00')
        ->and(MoneyFormatter::format(1234567890))->toBe('₹1,23,45,678.90');
});

test('formats negative amounts with a leading minus sign', function () {
    expect(MoneyFormatter::format(-12345600))->toBe('-₹1,23,456.00');
});
