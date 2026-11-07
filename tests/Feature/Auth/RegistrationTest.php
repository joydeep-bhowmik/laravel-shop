<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('a visitor can register and is asked to verify their email', function () {
    Notification::fake();

    Livewire::test('auth.register')
        ->set('name', 'Asha Verma')
        ->set('email', 'asha@example.com')
        ->set('phone', '9876543210')
        ->set('password', 'Sup3r-secret-pass')
        ->set('password_confirmation', 'Sup3r-secret-pass')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'asha@example.com')->firstOrFail();

    expect($user->name)->toBe('Asha Verma')
        ->and($user->phone)->toBe('9876543210')
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('registration rejects a duplicate email and mismatched passwords', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test('auth.register')
        ->set('name', 'Someone')
        ->set('email', 'taken@example.com')
        ->set('password', 'Sup3r-secret-pass')
        ->set('password_confirmation', 'different')
        ->call('register')
        ->assertHasErrors(['email', 'password']);
});
