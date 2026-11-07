<?php

use App\Enums\AdminRole;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\CustomerAdminService;
use App\Services\CustomerCartService;
use App\Services\MoneyFormatter;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** The customer's user id. */
    public int $customer;

    public string $couponCode = '';

    public string $productSearch = '';

    public int $quantity = 1;

    public function mount(int $customer): void
    {
        $this->customer = $customer;
    }

    #[Computed]
    public function account(): User
    {
        return User::query()->with('roles')->findOrFail($this->customer);
    }

    #[Computed]
    public function wishlist()
    {
        return Wishlist::query()
            ->with('product')
            ->where('user_id', $this->customer)
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function cartItems()
    {
        return CartItem::query()
            ->with(['productVariant.product'])
            ->whereHas('cart', fn ($query) => $query->where('user_id', $this->customer))
            ->oldest('id')
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        return app(CustomerCartService::class)->summary($this->account);
    }

    #[Computed]
    public function variantResults()
    {
        $search = trim($this->productSearch);

        if ($search === '') {
            return collect();
        }

        return ProductVariant::query()
            ->with('product')
            ->active()
            ->whereHas('product', fn ($query) => $query->published())
            ->where(fn ($query) => $query
                ->where('sku', 'like', "%{$search}%")
                ->orWhereHas('product', fn ($products) => $products->where('name', 'like', "%{$search}%")))
            ->limit(8)
            ->get();
    }

    public function addVariant(int $variantId, CustomerCartService $cart): void
    {
        $variant = ProductVariant::query()->findOrFail($variantId);

        try {
            $cart->addVariant($this->account, $variant, $this->quantity);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->reset('productSearch');
        $this->quantity = 1;
        $this->refreshCart();
        Flux::toast(variant: 'success', text: __('Added to the customer\'s cart.'));
    }

    public function setQuantity(int $itemId, int $quantity, CustomerCartService $cart): void
    {
        try {
            $cart->setQuantity($this->account, CartItem::query()->findOrFail($itemId), $quantity);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        $this->refreshCart();
    }

    public function removeItem(int $itemId, CustomerCartService $cart): void
    {
        try {
            $cart->removeItem($this->account, CartItem::query()->findOrFail($itemId));
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->refreshCart();
        Flux::toast(variant: 'success', text: __('Removed from the cart.'));
    }

    public function applyCoupon(CustomerCartService $cart): void
    {
        $this->validate(['couponCode' => ['required', 'string', 'max:40']]);

        try {
            $coupon = $cart->applyCoupon($this->account, $this->couponCode);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('couponCode', $exception->getMessage());

            return;
        }

        $this->reset('couponCode');
        $this->refreshCart();
        Flux::toast(variant: 'success', text: __('Coupon :code applied.', ['code' => $coupon->code]));
    }

    public function removeCoupon(CustomerCartService $cart): void
    {
        $cart->removeCoupon($this->account);
        $this->refreshCart();
    }

    public function toggleBlock(CustomerAdminService $customers): void
    {
        try {
            $customers->setBlocked(Auth::user(), $this->account, ! $this->account->is_blocked);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->account);
        Flux::toast(variant: 'success', text: $this->account->is_blocked ? __('Account blocked.') : __('Account unblocked.'));
    }

    private function refreshCart(): void
    {
        unset($this->cartItems, $this->summary, $this->wishlist);
    }

    public function roleNames(): string
    {
        return $this->account->roles->map(fn ($role) => AdminRole::tryFrom($role->name)?->label() ?? $role->name)->implode(', ');
    }
}; ?>

<div class="space-y-6">
    <flux:card class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ $this->account->name }}</flux:heading>
                <flux:text>{{ $this->account->email }}@if ($this->account->phone) · {{ $this->account->phone }}@endif</flux:text>
                <div class="mt-2 flex flex-wrap gap-2 text-sm">
                    <flux:badge size="sm" :color="$this->account->is_blocked ? 'red' : 'green'">{{ $this->account->is_blocked ? __('Blocked') : __('Active') }}</flux:badge>
                    <flux:badge size="sm" :color="$this->account->email_verified_at ? 'green' : 'zinc'">{{ $this->account->email_verified_at ? __('Email verified') : __('Email not verified') }}</flux:badge>
                    <flux:badge size="sm" color="blue">{{ $this->roleNames() ?: __('Customer') }}</flux:badge>
                </div>
            </div>
            @can('customers.block')
                @unless ($this->account->is(auth()->user()))
                    <x-confirm-button
                        name="confirm-block-customer"
                        :variant="$this->account->is_blocked ? 'primary' : 'danger'"
                        :label="$this->account->is_blocked ? __('Unblock account') : __('Block account')"
                        action="toggleBlock"
                        :title="$this->account->is_blocked ? __('Unblock this account?') : __('Block this account?')"
                        :message="$this->account->is_blocked ? __('The customer can sign in again.') : __('The customer will be signed out and cannot sign in until unblocked.')"
                    />
                @endunless
            @endcan
        </div>
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading>{{ __('Wishlist') }}</flux:heading>
            @forelse ($this->wishlist as $entry)
                <div class="flex items-center justify-between gap-3 text-sm" wire:key="wish-{{ $entry->id }}">
                    <a href="{{ route('admin.products.edit', ['product' => $entry->product_id]) }}" wire:navigate class="underline-offset-2 hover:underline">{{ $entry->product->name }}</a>
                    <flux:text class="text-xs">{{ __('Saved :date', ['date' => $entry->created_at->format('d M Y')]) }}</flux:text>
                </div>
            @empty
                <flux:text>{{ __('Nothing saved to the wishlist.') }}</flux:text>
            @endforelse
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>{{ __('Cart') }}</flux:heading>

            @forelse ($this->cartItems as $item)
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm" wire:key="cart-{{ $item->id }}">
                    <div>
                        {{ $item->productVariant->product->name }}
                        <span class="text-zinc-500">({{ $item->productVariant->sku }})</span>
                        <div class="text-xs text-zinc-500">{{ MoneyFormatter::format($item->productVariant->price_paise) }}</div>
                    </div>
                    @can('customers.support')
                        <div class="flex items-center gap-2">
                            <flux:input
                                type="number"
                                min="1"
                                max="99"
                                class="w-20"
                                :value="$item->quantity"
                                x-on:change="$wire.setQuantity({{ $item->id }}, parseInt($event.target.value, 10) || 0)"
                            />
                            <x-confirm-button
                                name="confirm-remove-cart-{{ $item->id }}"
                                size="xs"
                                :label="__('Remove')"
                                action="removeItem({{ $item->id }})"
                                :message="__('Remove this item from the customer\'s cart?')"
                            />
                        </div>
                    @else
                        <flux:text>× {{ $item->quantity }}</flux:text>
                    @endcan
                </div>
            @empty
                <flux:text>{{ __('The cart is empty.') }}</flux:text>
            @endforelse

            <div class="space-y-1 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-700">
                <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span>{{ MoneyFormatter::format($this->summary['subtotal']) }}</span></div>
                @if ($this->summary['coupon'])
                    <div class="flex justify-between text-green-700 dark:text-green-400">
                        <span>{{ __('Coupon :code', ['code' => $this->summary['coupon']]) }}</span>
                        <span>−{{ MoneyFormatter::format($this->summary['discount']) }}</span>
                    </div>
                @endif
                <div class="flex justify-between font-medium"><span>{{ __('Total') }}</span><span>{{ MoneyFormatter::format($this->summary['total']) }}</span></div>
            </div>

            @can('customers.support')
                @if ($this->summary['coupon'])
                    <flux:button size="xs" variant="ghost" wire:click="removeCoupon">{{ __('Remove coupon') }}</flux:button>
                @else
                    <form wire:submit="applyCoupon" class="flex items-start gap-2">
                        <flux:input wire:model="couponCode" :placeholder="__('Coupon code')" class="uppercase" />
                        <flux:button type="submit" size="sm">{{ __('Apply coupon') }}</flux:button>
                    </form>
                    <flux:error name="couponCode" />
                @endif

                <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Add a product to this cart') }}</flux:heading>
                    <div class="flex items-start gap-2">
                        <flux:input wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" :placeholder="__('Product name or SKU')" class="flex-1" />
                        <flux:input type="number" min="1" max="99" wire:model="quantity" class="w-20" />
                    </div>
                    @foreach ($this->variantResults as $variant)
                        <div class="flex items-center justify-between gap-3 text-sm" wire:key="variant-{{ $variant->id }}">
                            <span>
                                {{ $variant->product->name }} <span class="text-zinc-500">({{ $variant->sku }})</span>
                                <span class="text-xs text-zinc-500">· {{ __(':count in stock', ['count' => $variant->stock_quantity]) }}</span>
                            </span>
                            <flux:button size="xs" wire:click="addVariant({{ $variant->id }})">{{ __('Add') }}</flux:button>
                        </div>
                    @endforeach
                </div>
            @endcan
        </flux:card>
    </div>
</div>
