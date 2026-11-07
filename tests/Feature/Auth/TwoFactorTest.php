<?php

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

test('the TOTP code matches the RFC 6238 test vector', function () {
    // RFC 6238 appendix B: secret "12345678901234567890", T = 59 gives 94287082 with eight digits, so 287082 with six.
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    expect(app(TwoFactorService::class)->codeAt($secret, intdiv(59, 30)))->toBe('287082');
});

test('a code is accepted in the current period and one period either side only', function () {
    $service = app(TwoFactorService::class);
    $secret = $service->generateSecret();
    Carbon::setTestNow(Carbon::createFromTimestamp(1_700_000_000));
    $counter = intdiv(1_700_000_000, 30);

    expect($service->verify($secret, $service->codeAt($secret, $counter)))->toBeTrue()
        ->and($service->verify($secret, $service->codeAt($secret, $counter + 1)))->toBeTrue()
        ->and($service->verify($secret, $service->codeAt($secret, $counter + 2)))->toBeFalse()
        ->and($service->verify($secret, 'abcdef'))->toBeFalse();

    Carbon::setTestNow();
});

test('a recovery code works once', function () {
    $user = User::factory()->withTwoFactor()->create();
    $service = app(TwoFactorService::class);

    expect($service->consumeRecoveryCode($user, 'recovery-code-1'))->toBeTrue()
        ->and($service->consumeRecoveryCode($user->fresh(), 'recovery-code-1'))->toBeFalse();
});

test('a customer can switch two-factor on with a valid code', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $service = app(TwoFactorService::class);

    $component = Livewire::test('account.security')
        ->call('startTwoFactorSetup');

    $secret = $user->fresh()->two_factor_secret;
    expect($secret)->not->toBeNull()
        ->and($user->fresh()->hasTwoFactorEnabled())->toBeFalse();

    $component->set('confirmCode', $service->codeAt($secret, intdiv(now()->getTimestamp(), 30)))
        ->call('confirmTwoFactor')
        ->assertHasNoErrors();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->fresh()->two_factor_recovery_codes)->toHaveCount(8);
});

test('a wrong setup code leaves two-factor off', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('account.security')
        ->call('startTwoFactorSetup')
        ->set('confirmCode', '000000')
        ->call('confirmTwoFactor')
        ->assertHasErrors(['confirmCode']);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('login with two-factor completes after a valid authenticator code', function () {
    $service = app(TwoFactorService::class);
    $user = User::factory()->create([
        'two_factor_secret' => $service->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ]);

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    expect(Session::get('login.id'))->toBe($user->id);

    Livewire::test('auth.two-factor-challenge')
        ->set('code', $service->codeAt($user->two_factor_secret, intdiv(now()->getTimestamp(), 30)))
        ->call('verify')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('login with two-factor refuses a wrong code', function () {
    $user = User::factory()->create([
        'two_factor_secret' => app(TwoFactorService::class)->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ]);
    Session::put('login.id', $user->id);

    Livewire::test('auth.two-factor-challenge')
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors(['code']);

    $this->assertGuest();
});
