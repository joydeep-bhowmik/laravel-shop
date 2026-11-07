<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('changing the email address asks for verification of the new address', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);
    $this->actingAs($user);

    Livewire::test('account.profile')
        ->set('email', 'new@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->email)->toBe('new@example.com')
        ->and($user->fresh()->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('the profile keeps its verification when only the name changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('account.profile')
        ->set('name', 'Renamed Person')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Renamed Person')
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('changing the password needs the current password', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('account.security')
        ->set('currentPassword', 'not-my-password')
        ->set('password', 'Fresh-pass-12345')
        ->set('password_confirmation', 'Fresh-pass-12345')
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('the password can be changed with the current password', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('account.security')
        ->set('currentPassword', 'password')
        ->set('password', 'Fresh-pass-12345')
        ->set('password_confirmation', 'Fresh-pass-12345')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('Fresh-pass-12345', $user->fresh()->password))->toBeTrue();
});
