<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\URL;

test('verification is optional by default, so unverified customers can use account pages', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('account.profile'))->assertOk();
});

test('when the store requires verification, unverified customers are sent to the notice', function () {
    app(SettingsService::class)->set('auth.require_email_verification', '1');
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('account.profile'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->create())->get(route('account.profile'))->assertOk();
});

test('a signed verification link marks the account verified', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)
        ->assertRedirect(route('dashboard', ['verified' => 1]));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a link with a wrong hash does not verify anything', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an unsigned verification link is refused', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
        ->assertForbidden();
});
