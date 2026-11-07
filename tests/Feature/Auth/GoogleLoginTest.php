<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

function fakeGoogleUser(string $id, string $email, string $name = 'Google Person'): void
{
    $user = (new GoogleUser)->map(['id' => $id, 'email' => $email, 'name' => $name]);

    Socialite::shouldReceive('driver->user')->andReturn($user);
}

test('a new Google account creates a verified customer and logs them in', function () {
    fakeGoogleUser('g-100', 'new.person@example.com', 'New Person');

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'new.person@example.com')->firstOrFail();

    expect($user->google_id)->toBe('g-100')
        ->and($user->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('a Google sign-in links to an existing account with the same email', function () {
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    fakeGoogleUser('g-200', 'existing@example.com');

    $this->get(route('google.callback'));

    expect($existing->fresh()->google_id)->toBe('g-200');
    $this->assertAuthenticatedAs($existing);
});

test('a blocked account cannot sign in with Google', function () {
    User::factory()->create(['email' => 'blocked@example.com', 'is_blocked' => true]);
    fakeGoogleUser('g-300', 'blocked@example.com');

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
});
