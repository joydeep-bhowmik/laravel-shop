<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    public string $token = '';

    #[Url]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'password_confirmation' => $this->password_confirmation, 'token' => $this->token],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', __('Your password has been reset. You can log in now.'));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-md space-y-6">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Choose a new password') }}</h1>

    <form wire:submit="resetPassword" class="space-y-4">
        <flux:field>
            <flux:label>{{ __('Email') }}</flux:label>
            <flux:input type="email" wire:model="email" autocomplete="email" required />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('New password') }}</flux:label>
            <flux:input type="password" wire:model="password" autocomplete="new-password" required />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Confirm new password') }}</flux:label>
            <flux:input type="password" wire:model="password_confirmation" autocomplete="new-password" required />
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full">{{ __('Reset password') }}</flux:button>
    </form>
</div>
