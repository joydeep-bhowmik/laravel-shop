<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

test('unverified customers cannot open account pages', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('account.profile'))
        ->assertRedirect(route('verification.notice'));
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
