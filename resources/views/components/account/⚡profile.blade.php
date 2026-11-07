<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|regex:/^[6-9]\d{9}$/')]
    public string $phone = '';

    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->phone = (string) Auth::user()->phone;
        $this->email = Auth::user()->email;
    }

    public function save(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->validate();
        $this->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $emailChanged = $this->email !== $user->email;

        $user->forceFill([
            'name' => $this->name,
            'phone' => $this->phone ?: null,
            'email' => $this->email,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        Flux::toast(variant: 'success', text: __('Profile saved.'));
    }
}; ?>

<form wire:submit="save" class="max-w-xl space-y-4">
    <flux:field>
        <flux:label>{{ __('Full name') }}</flux:label>
        <flux:input wire:model="name" required />
        <flux:error name="name" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Email') }}</flux:label>
        <flux:input type="email" wire:model="email" required />
        <flux:description>{{ __('Changing your email means verifying the new address again.') }}</flux:description>
        <flux:error name="email" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Mobile number') }}</flux:label>
        <flux:input type="tel" wire:model="phone" inputmode="numeric" />
        <flux:error name="phone" />
    </flux:field>

    <flux:button type="submit" variant="primary">{{ __('Save profile') }}</flux:button>
</form>
