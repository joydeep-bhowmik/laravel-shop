<?php

use App\Models\User;
use App\Services\TwoFactorService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|string')]
    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $confirmCode = '';

    public string $disablePassword = '';

    /** Setup secret shown while enabling two-factor, before it is confirmed. */
    public ?string $pendingSecret = null;

    public string $pendingUri = '';

    /** @var array<int, string> Recovery codes, shown once after they are created. */
    public array $newRecoveryCodes = [];

    public function mount(TwoFactorService $twoFactor): void
    {
        $user = Auth::user();

        if (! $user->hasTwoFactorEnabled() && $user->two_factor_secret !== null) {
            $this->pendingSecret = $user->two_factor_secret;
            $this->pendingUri = $twoFactor->provisioningUri($user, $this->pendingSecret);
        }
    }

    public function updatePassword(): void
    {
        $this->validate(['currentPassword' => ['required']]);
        $this->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($this->currentPassword, $user->password)) {
            throw ValidationException::withMessages(['currentPassword' => __('That is not your current password.')]);
        }

        $user->forceFill(['password' => Hash::make($this->password)])->save();
        $this->reset('currentPassword', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    public function startTwoFactorSetup(TwoFactorService $twoFactor): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->pendingSecret = $twoFactor->generateSecret();
        $this->pendingUri = $twoFactor->provisioningUri($user, $this->pendingSecret);

        $user->forceFill([
            'two_factor_secret' => $this->pendingSecret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    public function confirmTwoFactor(TwoFactorService $twoFactor): void
    {
        $this->validate(['confirmCode' => ['required', 'digits:6']]);

        /** @var User $user */
        $user = Auth::user();

        if ($this->pendingSecret === null || ! $twoFactor->verify($this->pendingSecret, $this->confirmCode)) {
            throw ValidationException::withMessages(['confirmCode' => __('That code did not match. Check the time on your phone and try again.')]);
        }

        $this->newRecoveryCodes = $twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->newRecoveryCodes,
        ])->save();

        $this->pendingSecret = null;
        $this->pendingUri = '';
        $this->reset('confirmCode');

        Flux::toast(variant: 'success', text: __('Two-factor sign-in is on. Save your recovery codes.'));
    }

    public function disableTwoFactor(): void
    {
        $this->validate(['disablePassword' => ['required']]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($this->disablePassword, $user->password)) {
            throw ValidationException::withMessages(['disablePassword' => __('That is not your current password.')]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->reset('disablePassword', 'pendingSecret', 'pendingUri', 'newRecoveryCodes');

        Flux::toast(variant: 'success', text: __('Two-factor sign-in is off.'));
    }

    public function regenerateRecoveryCodes(TwoFactorService $twoFactor): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->newRecoveryCodes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $this->newRecoveryCodes])->save();

        Flux::toast(variant: 'success', text: __('New recovery codes created. The old ones no longer work.'));
    }
}; ?>

<div class="max-w-xl space-y-10">
    <section class="space-y-4">
        <h2 class="text-lg font-semibold">{{ __('Change password') }}</h2>
        <form wire:submit="updatePassword" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Current password') }}</flux:label>
                <flux:input type="password" wire:model="currentPassword" autocomplete="current-password" />
                <flux:error name="currentPassword" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('New password') }}</flux:label>
                <flux:input type="password" wire:model="password" autocomplete="new-password" />
                <flux:error name="password" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Confirm new password') }}</flux:label>
                <flux:input type="password" wire:model="password_confirmation" autocomplete="new-password" />
            </flux:field>
            <flux:button type="submit" variant="primary">{{ __('Update password') }}</flux:button>
        </form>
    </section>

    <section class="space-y-4">
        <h2 class="text-lg font-semibold">{{ __('Two-factor authentication') }}</h2>

        @if (auth()->user()->hasTwoFactorEnabled())
            <flux:callout variant="success">{{ __('On. Your account asks for an authenticator code when you log in.') }}</flux:callout>

            @if ($newRecoveryCodes !== [])
                <flux:callout variant="warning">
                    <p class="mb-2 font-medium">{{ __('Recovery codes. Each works once. Store them somewhere safe.') }}</p>
                    <ul class="grid grid-cols-2 gap-1 font-mono text-sm">
                        @foreach ($newRecoveryCodes as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                </flux:callout>
            @endif

            <div class="flex flex-wrap items-end gap-3">
                <flux:button wire:click="regenerateRecoveryCodes" variant="ghost">{{ __('New recovery codes') }}</flux:button>
            </div>

            <form wire:submit="disableTwoFactor" class="flex flex-wrap items-end gap-3">
                <flux:field class="w-64">
                    <flux:label>{{ __('Password to turn off') }}</flux:label>
                    <flux:input type="password" wire:model="disablePassword" autocomplete="current-password" />
                    <flux:error name="disablePassword" />
                </flux:field>
                <flux:button type="submit" variant="danger">{{ __('Turn off') }}</flux:button>
            </form>
        @elseif ($pendingSecret)
            <div class="space-y-3 text-sm">
                <p>{{ __('In your authenticator app, add a new account and enter this key:') }}</p>
                <p class="select-all rounded-md bg-zinc-100 p-3 font-mono tracking-wider dark:bg-zinc-800">{{ $pendingSecret }}</p>
                <p class="text-zinc-500">{{ __('Or open this link on your phone:') }} <a class="break-all underline" href="{{ $pendingUri }}">{{ $pendingUri }}</a></p>
            </div>

            <form wire:submit="confirmTwoFactor" class="flex flex-wrap items-end gap-3">
                <flux:field class="w-48">
                    <flux:label>{{ __('6-digit code') }}</flux:label>
                    <flux:input inputmode="numeric" autocomplete="one-time-code" wire:model="confirmCode" />
                    <flux:error name="confirmCode" />
                </flux:field>
                <flux:button type="submit" variant="primary">{{ __('Confirm and turn on') }}</flux:button>
            </form>
        @else
            <flux:text>{{ __('Add a second step at login with an authenticator app.') }}</flux:text>
            <flux:button wire:click="startTwoFactorSetup" variant="primary">{{ __('Set up two-factor') }}</flux:button>
        @endif
    </section>
</div>
