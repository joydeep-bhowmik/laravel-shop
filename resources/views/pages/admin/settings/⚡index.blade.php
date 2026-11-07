<?php

use Livewire\Attributes\Layout;

use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings')] #[Layout('layouts::admin')] class extends Component {
    public string $storeName = '';

    public string $storeEmail = '';

    public string $storePhone = '';

    public string $storeAddress = '';

    public string $storeGstin = '';

    public string $storeState = '';

    public string $shippingFlatRupees = '';

    public string $shippingFreeThresholdRupees = '';

    public int $shippingStandardDays = 5;

    public bool $codEnabled = true;

    public string $codMaxOrderRupees = '';

    public string $razorpayMode = 'test';

    public string $razorpayKeyId = '';

    /** Write-only: left blank to keep the stored value. */
    public string $razorpayKeySecret = '';

    /** Write-only: left blank to keep the stored value. */
    public string $razorpayWebhookSecret = '';

    public bool $hasKeySecret = false;

    public bool $hasWebhookSecret = false;

    public string $emailFromName = '';

    public string $emailFromAddress = '';

    public function mount(SettingsService $settings): void
    {
        Gate::authorize('settings.manage');

        $this->storeName = (string) $settings->get('store.name');
        $this->storeEmail = (string) $settings->get('store.email');
        $this->storePhone = (string) $settings->get('store.phone');
        $this->storeAddress = (string) $settings->get('store.address');
        $this->storeGstin = (string) $settings->get('store.gstin');
        $this->storeState = (string) $settings->get('store.state');
        $this->shippingFlatRupees = number_format($settings->int('shipping.flat_rate_paise') / 100, 2, '.', '');
        $this->shippingFreeThresholdRupees = number_format($settings->int('shipping.free_threshold_paise') / 100, 2, '.', '');
        $this->shippingStandardDays = $settings->int('shipping.standard_days');
        $this->codEnabled = $settings->bool('cod.enabled');
        $this->codMaxOrderRupees = number_format($settings->int('cod.max_order_paise') / 100, 2, '.', '');
        $this->razorpayMode = (string) $settings->get('razorpay.mode');
        $this->razorpayKeyId = (string) $settings->get('razorpay.key_id');
        $this->hasKeySecret = $settings->get('razorpay.key_secret') !== '';
        $this->hasWebhookSecret = $settings->get('razorpay.webhook_secret') !== '';
        $this->emailFromName = (string) $settings->get('email.from_name');
        $this->emailFromAddress = (string) $settings->get('email.from_address');
    }

    public function save(SettingsService $settings): void
    {
        Gate::authorize('settings.manage');

        $validated = $this->validate([
            'storeName' => ['required', 'string', 'max:120'],
            'storeEmail' => ['nullable', 'email', 'max:160'],
            'storePhone' => ['nullable', 'string', 'max:20'],
            'storeAddress' => ['nullable', 'string', 'max:500'],
            'storeGstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'storeState' => ['required', 'string', 'max:60'],
            'shippingFlatRupees' => ['required', 'regex:/^\d{1,6}(\.\d{1,2})?$/'],
            'shippingFreeThresholdRupees' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'shippingStandardDays' => ['required', 'integer', 'between:1,30'],
            'codMaxOrderRupees' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'razorpayMode' => ['required', 'in:test,live'],
            'razorpayKeyId' => ['nullable', 'string', 'max:100'],
            'razorpayKeySecret' => ['nullable', 'string', 'max:200'],
            'razorpayWebhookSecret' => ['nullable', 'string', 'max:200'],
            'emailFromName' => ['required', 'string', 'max:120'],
            'emailFromAddress' => ['required', 'email', 'max:160'],
        ]);

        $settings->set('store.name', $validated['storeName']);
        $settings->set('store.email', $validated['storeEmail'] ?? '');
        $settings->set('store.phone', $validated['storePhone'] ?? '');
        $settings->set('store.address', $validated['storeAddress'] ?? '');
        $settings->set('store.gstin', strtoupper((string) ($validated['storeGstin'] ?? '')));
        $settings->set('store.state', $validated['storeState']);
        $settings->set('shipping.flat_rate_paise', (string) \App\Services\MoneyFormatter::toPaise($validated['shippingFlatRupees']));
        $settings->set('shipping.free_threshold_paise', (string) \App\Services\MoneyFormatter::toPaise($validated['shippingFreeThresholdRupees']));
        $settings->set('shipping.standard_days', (string) $validated['shippingStandardDays']);
        $settings->set('cod.enabled', $this->codEnabled ? '1' : '0');
        $settings->set('cod.max_order_paise', (string) \App\Services\MoneyFormatter::toPaise($validated['codMaxOrderRupees']));
        $settings->set('razorpay.mode', $validated['razorpayMode']);
        $settings->set('razorpay.key_id', $validated['razorpayKeyId'] ?? '');

        if (filled($validated['razorpayKeySecret'] ?? null)) {
            $settings->set('razorpay.key_secret', $validated['razorpayKeySecret']);
        }

        if (filled($validated['razorpayWebhookSecret'] ?? null)) {
            $settings->set('razorpay.webhook_secret', $validated['razorpayWebhookSecret']);
        }

        $settings->set('email.from_name', $validated['emailFromName']);
        $settings->set('email.from_address', $validated['emailFromAddress']);

        $this->reset('razorpayKeySecret', 'razorpayWebhookSecret');
        $this->hasKeySecret = $settings->get('razorpay.key_secret') !== '';
        $this->hasWebhookSecret = $settings->get('razorpay.webhook_secret') !== '';

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }
}; ?>

<div class="space-y-6">
    <form wire:submit="save" class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading>{{ __('Store') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Store name') }}</flux:label>
                <flux:input wire:model="storeName" />
                <flux:error name="storeName" />
            </flux:field>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Support email') }}</flux:label>
                    <flux:input type="email" wire:model="storeEmail" />
                    <flux:error name="storeEmail" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Phone') }}</flux:label>
                    <flux:input wire:model="storePhone" />
                    <flux:error name="storePhone" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Registered address') }}</flux:label>
                <flux:textarea wire:model="storeAddress" rows="3" />
                <flux:error name="storeAddress" />
            </flux:field>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('GSTIN') }}</flux:label>
                    <flux:input wire:model="storeGstin" class="uppercase" />
                    <flux:error name="storeGstin" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Home state (GST)') }}</flux:label>
                    <flux:input wire:model="storeState" />
                    <flux:description>{{ __('Orders shipped within this state use CGST + SGST, others use IGST.') }}</flux:description>
                    <flux:error name="storeState" />
                </flux:field>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>{{ __('Shipping') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Flat shipping rate (₹)') }}</flux:label>
                    <flux:input wire:model="shippingFlatRupees" inputmode="decimal" />
                    <flux:error name="shippingFlatRupees" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Free shipping above (₹)') }}</flux:label>
                    <flux:input wire:model="shippingFreeThresholdRupees" inputmode="decimal" />
                    <flux:error name="shippingFreeThresholdRupees" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Standard delivery (days)') }}</flux:label>
                <flux:input type="number" min="1" max="30" wire:model="shippingStandardDays" />
                <flux:error name="shippingStandardDays" />
            </flux:field>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>{{ __('Cash on delivery') }}</flux:heading>
            <flux:switch wire:model="codEnabled" :label="__('Offer cash on delivery')" />
            <flux:field>
                <flux:label>{{ __('Largest order allowed for COD (₹)') }}</flux:label>
                <flux:input wire:model="codMaxOrderRupees" inputmode="decimal" />
                <flux:error name="codMaxOrderRupees" />
            </flux:field>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>{{ __('Razorpay') }}</flux:heading>
            <flux:callout variant="secondary" icon="lock-closed">
                {{ __('Secrets are encrypted at rest. Leave a secret blank to keep the stored value.') }}
            </flux:callout>
            <flux:field>
                <flux:label>{{ __('Mode') }}</flux:label>
                <flux:select wire:model="razorpayMode">
                    <flux:select.option value="test">{{ __('Test') }}</flux:select.option>
                    <flux:select.option value="live">{{ __('Live') }}</flux:select.option>
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Key ID') }}</flux:label>
                <flux:input wire:model="razorpayKeyId" />
                <flux:error name="razorpayKeyId" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Key secret') }} @if ($hasKeySecret)<span class="text-xs text-green-700">({{ __('saved') }})</span>@endif</flux:label>
                <flux:input type="password" wire:model="razorpayKeySecret" autocomplete="new-password" />
                <flux:error name="razorpayKeySecret" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Webhook secret') }} @if ($hasWebhookSecret)<span class="text-xs text-green-700">({{ __('saved') }})</span>@endif</flux:label>
                <flux:input type="password" wire:model="razorpayWebhookSecret" autocomplete="new-password" />
                <flux:error name="razorpayWebhookSecret" />
            </flux:field>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>{{ __('Email') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('From name') }}</flux:label>
                    <flux:input wire:model="emailFromName" />
                    <flux:error name="emailFromName" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('From address') }}</flux:label>
                    <flux:input type="email" wire:model="emailFromAddress" />
                    <flux:error name="emailFromAddress" />
                </flux:field>
            </div>
        </flux:card>

        <div class="flex justify-end lg:col-span-2">
            <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
        </div>
    </form>
</div>
