<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = User::query()->where('email', $this->email)->firstOrFail();

        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'email' => __('This account has been blocked. Contact support for help.'),
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            Session::put('login.id', $user->id);
            Session::put('login.remember', $this->remember);

            $this->redirectRoute('two-factor.challenge', navigate: true);

            return;
        }

        Auth::login($user, $this->remember);
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-md space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Log in') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Welcome back.') }}</p>
    </div>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <form wire:submit="login" class="space-y-4">
        <flux:field>
            <flux:label>{{ __('Email') }}</flux:label>
            <flux:input type="email" wire:model="email" autocomplete="email" required />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <div class="flex items-center justify-between">
                <flux:label>{{ __('Password') }}</flux:label>
                <a href="{{ route('password.request') }}" wire:navigate class="text-sm underline-offset-2 hover:underline">{{ __('Forgot password?') }}</a>
            </div>
            <flux:input type="password" wire:model="password" autocomplete="current-password" required />
            <flux:error name="password" />
        </flux:field>

        <flux:checkbox wire:model="remember" :label="__('Keep me signed in')" />

        <flux:button type="submit" variant="primary" class="w-full">{{ __('Log in') }}</flux:button>
    </form>

    <div class="relative text-center text-sm text-zinc-500">
        <span class="bg-white px-2 dark:bg-zinc-900">{{ __('or') }}</span>
    </div>

    <a href="{{ route('google.redirect') }}" class="flex w-full items-center justify-center gap-2 rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800">
        {{ __('Continue with Google') }}
    </a>

    <p class="text-center text-sm text-zinc-500">
        {{ __('New here?') }}
        <a href="{{ route('register') }}" wire:navigate class="font-medium underline-offset-2 hover:underline">{{ __('Create an account') }}</a>
    </p>
</div>
