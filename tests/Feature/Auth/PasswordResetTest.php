<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

test('requesting a reset sends a link and says the same thing for unknown emails', function () {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::test('auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendLink')
        ->assertSet('sent', __('If that address has an account, a reset link is on its way.'));

    Notification::assertSentTo($user, ResetPassword::class);

    Livewire::test('auth.forgot-password')
        ->set('email', 'nobody@example.com')
        ->call('sendLink')
        ->assertHasNoErrors();
});

test('a valid token sets a new password', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    Livewire::test('auth.reset-password', ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'Brand-new-pass-1')
        ->set('password_confirmation', 'Brand-new-pass-1')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('Brand-new-pass-1', $user->fresh()->password))->toBeTrue();
});

test('a bad token leaves the password alone', function () {
    $user = User::factory()->create();

    Livewire::test('auth.reset-password', ['token' => 'not-a-real-token'])
        ->set('email', $user->email)
        ->set('password', 'Brand-new-pass-1')
        ->set('password_confirmation', 'Brand-new-pass-1')
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
