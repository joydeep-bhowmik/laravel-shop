<?php

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('nullable|string|max:20')]
    public string $code = '';

    #[Validate('nullable|string|max:40')]
    public string $recoveryCode = '';

    public function verify(TwoFactorService $twoFactor): void
    {
        $this->validate();

        $pendingId = Session::get('login.id');

        if ($pendingId === null) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $throttleKey = 'two-factor:'.$pendingId;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Try again in a minute.')]);
        }

        /** @var User $user */
        $user = User::query()->findOrFail($pendingId);

        $valid = $this->recoveryCode !== ''
            ? $twoFactor->consumeRecoveryCode($user, $this->recoveryCode)
            : $twoFactor->verify((string) $user->two_factor_secret, $this->code);

        if (! $valid) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages(['code' => __('That code is not valid.')]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) Session::pull('login.remember', false);
        Session::forget('login.id');

        Auth::login($user, $remember);
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-md space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Two-factor check') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Enter the 6-digit code from your authenticator app, or use a recovery code.') }}</p>
    </div>

    <form wire:submit="verify" class="space-y-4">
        <flux:field>
            <flux:label>{{ __('Authentication code') }}</flux:label>
            <flux:input inputmode="numeric" autocomplete="one-time-code" wire:model="code" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Or recovery code') }}</flux:label>
            <flux:input autocomplete="off" wire:model="recoveryCode" />
            <flux:error name="code" />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">{{ __('Verify') }}</flux:button>
    </form>
</div>
