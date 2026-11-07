<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    public ?string $sent = null;

    public function sendLink(): void
    {
        $this->validate();

        // The same message is shown whether or not the address exists, so accounts cannot be probed.
        Password::sendResetLink(['email' => $this->email]);

        $this->sent = __('If that address has an account, a reset link is on its way.');
    }
}; ?>

<div class="mx-auto w-full max-w-md space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Reset your password') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Enter your email and we will send you a reset link.') }}</p>
    </div>

    @if ($sent)
        <flux:callout variant="success">{{ $sent }}</flux:callout>
    @endif

    <form wire:submit="sendLink" class="space-y-4">
        <flux:field>
            <flux:label>{{ __('Email') }}</flux:label>
            <flux:input type="email" wire:model="email" autocomplete="email" required />
            <flux:error name="email" />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">{{ __('Send reset link') }}</flux:button>
    </form>

    <p class="text-center text-sm"><a href="{{ route('login') }}" wire:navigate class="underline-offset-2 hover:underline">{{ __('Back to log in') }}</a></p>
</div>
