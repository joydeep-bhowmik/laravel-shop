<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('login page renders for guests', function () {
    $this->get(route('login'))->assertOk();
});

test('signed in users are sent away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect();
});

test('a customer can log in with the right password', function () {
    $user = User::factory()->create();

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('a wrong password is rejected with a generic message', function () {
    $user = User::factory()->create();

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('blocked accounts cannot log in even with the right password', function () {
    $user = User::factory()->create(['is_blocked' => true]);

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('repeated failures are throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'nope')
            ->call('login');
    }

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'nope')
        ->call('login')
        ->assertHasErrors(['email']);

    expect(RateLimiter::tooManyAttempts(Str::transliterate(Str::lower($user->email).'|127.0.0.1'), 5))->toBeTrue();
});

test('accounts with two-factor enabled are sent to the code step before login', function () {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertGuest();
    expect(Session::get('login.id'))->toBe($user->id);
});

test('logging out ends the session', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});
