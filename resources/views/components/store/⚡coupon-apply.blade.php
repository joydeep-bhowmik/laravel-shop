<?php

use App\Models\Coupon;
use App\Models\User;
use App\Services\CouponService;
use App\Services\MoneyFormatter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** Order subtotal in paise that the coupon is checked against. */
    public int $subtotalPaise = 0;

    public string $code = '';

    public ?string $appliedCode = null;

    public function mount(int $subtotalPaise): void
    {
        $this->subtotalPaise = $subtotalPaise;
        $this->appliedCode = session('checkout.coupon');
    }

    /**
     * The rupee amount the applied coupon takes off the current subtotal.
     */
    #[Computed]
    public function discount(): int
    {
        $coupon = $this->appliedCode === null ? null : Coupon::query()->code($this->appliedCode)->first();

        return $coupon === null ? 0 : app(CouponService::class)->discountFor($coupon, $this->subtotalPaise);
    }

    public function apply(CouponService $coupons): void
    {
        $this->validate(['code' => ['required', 'string', 'max:40']]);

        $user = Auth::user();

        if (! $user instanceof User) {
            $this->addError('code', __('Log in to use a coupon.'));

            return;
        }

        try {
            $coupon = $coupons->validate($this->code, $user, $this->subtotalPaise);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        session()->put('checkout.coupon', $coupon->code);

        $this->appliedCode = $coupon->code;
        $this->reset('code');
        unset($this->discount);
    }

    public function remove(): void
    {
        session()->forget('checkout.coupon');

        $this->appliedCode = null;
        unset($this->discount);
    }
}; ?>

<div class="space-y-2">
    @if ($appliedCode)
        <div class="flex items-center justify-between gap-3 rounded-md border border-green-600/30 bg-green-50 px-3 py-2 text-sm dark:bg-green-950/30">
            <span>
                {{ __('Coupon :code applied', ['code' => $appliedCode]) }}
                <span class="font-medium">−{{ MoneyFormatter::format($this->discount) }}</span>
            </span>
            <button type="button" wire:click="remove" class="text-zinc-600 underline-offset-2 hover:underline dark:text-zinc-300">{{ __('Remove') }}</button>
        </div>
    @else
        <form wire:submit="apply" class="flex items-start gap-2">
            <div class="flex-1">
                <label for="coupon-code" class="sr-only">{{ __('Coupon code') }}</label>
                <input id="coupon-code" type="text" wire:model="code" autocomplete="off" placeholder="{{ __('Coupon code') }}"
                    class="w-full rounded-md border-zinc-300 text-sm uppercase dark:border-zinc-600 dark:bg-zinc-800">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-md border border-zinc-900 px-4 py-2 text-sm font-medium hover:bg-zinc-900 hover:text-white dark:border-white dark:hover:bg-white dark:hover:text-zinc-900">{{ __('Apply') }}</button>
        </form>
    @endif
</div>
