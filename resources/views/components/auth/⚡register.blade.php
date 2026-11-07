<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

new class extends Component {
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'password' => Hash::make($this->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $this->redirectRoute('verification.notice', navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-md space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Create your account') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('We will send a link to verify your email.') }}</p>
    </div>

    <form wire:submit="register" class="space-y-4">
        <flux:field>
            <flux:label>{{ __('Full name') }}</flux:label>
            <flux:input wire:model="name" autocomplete="name" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Email') }}</flux:label>
            <flux:input type="email" wire:model="email" autocomplete="email" required />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mobile number (optional)') }}</flux:label>
            <flux:input type="tel" wire:model="phone" inputmode="numeric" autocomplete="tel" />
            <flux:error name="phone" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Password') }}</flux:label>
            <flux:input type="password" wire:model="password" autocomplete="new-password" required />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Confirm password') }}</flux:label>
            <flux:input type="password" wire:model="password_confirmation" autocomplete="new-password" required />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">{{ __('Create account') }}</flux:button>
    </form>

    <p class="text-center text-sm text-zinc-500">
        {{ __('Already registered?') }}
        <a href="{{ route('login') }}" wire:navigate class="font-medium underline-offset-2 hover:underline">{{ __('Log in') }}</a>
    </p>
</div>
