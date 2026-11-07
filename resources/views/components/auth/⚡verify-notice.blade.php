<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component {
    public function resend(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $key = 'verification-resend:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->addError('resend', __('Please wait a minute before requesting another link.'));

            return;
        }

        RateLimiter::hit($key, 60);
        $user->sendEmailVerificationNotification();

        session()->flash('status', __('A new verification link has been sent to your email.'));
    }
}; ?>

<div class="mx-auto w-full max-w-md space-y-6">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Verify your email') }}</h1>
    <p class="text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('We sent a link to :email. Open it to finish creating your account.', ['email' => auth()->user()->email]) }}
    </p>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @error('resend')
        <flux:callout variant="warning">{{ $message }}</flux:callout>
    @enderror

    <div class="flex items-center gap-3">
        <flux:button wire:click="resend" variant="primary">{{ __('Resend verification email') }}</flux:button>
        <flux:button variant="ghost" x-on:click="$flux.modal('confirm-logout').show()">{{ __('Log out') }}</flux:button>
    </div>
</div>
