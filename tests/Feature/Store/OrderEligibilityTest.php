<?php

use App\Models\User;
use App\Services\OrderEligibilityService;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;

test('unverified customers can order by default', function () {
    $user = User::factory()->unverified()->create();

    app(OrderEligibilityService::class)->ensureCanOrder($user);

    expect(app(SettingsService::class)->bool('checkout.require_verified_email'))->toBeFalse();
});

test('when the setting is on, an unverified customer is refused', function () {
    app(SettingsService::class)->set('checkout.require_verified_email', '1');
    $user = User::factory()->unverified()->create();

    app(OrderEligibilityService::class)->ensureCanOrder($user);
})->throws(InvalidArgumentException::class, 'Verify your email address');

test('when the setting is on, a verified customer can order', function () {
    app(SettingsService::class)->set('checkout.require_verified_email', '1');
    $user = User::factory()->create(['email_verified_at' => Carbon::now()]);

    app(OrderEligibilityService::class)->ensureCanOrder($user);

    expect(true)->toBeTrue();
});
