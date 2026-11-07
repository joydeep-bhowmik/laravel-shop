<?php

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;

test('encrypted settings are stored as ciphertext and read back in plain text', function () {
    $settings = app(SettingsService::class);

    $settings->set('razorpay.key_secret', 'rzp_test_secret_value');

    $row = Setting::query()->where('key', 'razorpay.key_secret')->firstOrFail();

    expect($row->getRawOriginal('value'))->not->toBe('rzp_test_secret_value')
        ->and(Crypt::decryptString($row->getRawOriginal('value')))->toBe('rzp_test_secret_value')
        ->and($settings->get('razorpay.key_secret'))->toBe('rzp_test_secret_value');
});

test('plain settings fall back to their declared default', function () {
    expect(app(SettingsService::class)->int('shipping.free_threshold_paise'))->toBe(49900);
});

test('updating a setting is visible on the next read', function () {
    $settings = app(SettingsService::class);

    expect($settings->get('store.name'))->toBe('Our Store');

    $settings->set('store.name', 'Bazaar Bhai');

    expect($settings->get('store.name'))->toBe('Bazaar Bhai');
});

test('unknown setting keys are rejected', function () {
    app(SettingsService::class)->set('made.up', 'value');
})->throws(InvalidArgumentException::class);
