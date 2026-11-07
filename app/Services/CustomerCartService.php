<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Lets support staff work on a customer's cart and coupons from the admin panel.
 *
 * Every change goes through the same stock and coupon rules the storefront will use at checkout.
 */
class CustomerCartService
{
    private const MAX_QUANTITY_PER_LINE = 99;

    public function __construct(private CouponService $coupons) {}

    /**
     * Add a variant to a customer's cart, creating the cart when it does not exist yet.
     *
     * @throws InvalidArgumentException
     */
    public function addVariant(User $customer, ProductVariant $variant, int $quantity): void
    {
        Gate::authorize('customers.support');

        $this->assertQuantity($quantity);

        $variant->loadMissing('product');

        if (! $variant->is_active || $variant->product->status !== ProductStatus::Published) {
            throw new InvalidArgumentException(__('This product is not available to order.'));
        }

        DB::transaction(function () use ($customer, $variant, $quantity): void {
            $cart = Cart::query()->firstOrCreate(['user_id' => $customer->getKey()]);

            $item = CartItem::query()->firstOrNew(
                ['cart_id' => $cart->getKey(), 'product_variant_id' => $variant->getKey()],
                ['quantity' => 0],
            );

            $newQuantity = $item->quantity + $quantity;

            $this->assertInStock($variant, $newQuantity);

            $item->quantity = $newQuantity;
            $item->save();
        });
    }

    /**
     * Set the quantity of a cart line. A quantity of zero removes the line.
     *
     * @throws InvalidArgumentException
     */
    public function setQuantity(User $customer, CartItem $item, int $quantity): void
    {
        Gate::authorize('customers.support');

        if ($quantity < 1) {
            $this->removeItem($customer, $item);

            return;
        }

        $this->assertQuantity($quantity);
        $this->assertOwnsItem($customer, $item);

        $this->assertInStock($item->productVariant, $quantity);

        $item->update(['quantity' => $quantity]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function removeItem(User $customer, CartItem $item): void
    {
        Gate::authorize('customers.support');

        $this->assertOwnsItem($customer, $item);

        $item->delete();
    }

    /**
     * Apply a coupon to a customer's cart after the same checks a customer would face.
     *
     * @throws InvalidArgumentException
     */
    public function applyCoupon(User $customer, string $code): Coupon
    {
        Gate::authorize('customers.support');

        $cart = $this->cartFor($customer);

        if ($cart === null || $cart->items()->doesntExist()) {
            throw new InvalidArgumentException(__('Add items to the cart before applying a coupon.'));
        }

        $coupon = $this->coupons->validate($code, $customer, $this->subtotalPaise($cart));

        $cart->update(['coupon_id' => $coupon->getKey()]);

        return $coupon;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function removeCoupon(User $customer): void
    {
        Gate::authorize('customers.support');

        $this->cartFor($customer)?->update(['coupon_id' => null]);
    }

    /**
     * Totals for display. A coupon that is no longer active or no longer meets its minimum gives no discount.
     *
     * @return array{subtotal: int, discount: int, total: int, coupon: ?string}
     */
    public function summary(User $customer): array
    {
        $cart = $this->cartFor($customer);

        if ($cart === null) {
            return ['subtotal' => 0, 'discount' => 0, 'total' => 0, 'coupon' => null];
        }

        $subtotal = $this->subtotalPaise($cart);
        $coupon = $cart->coupon;
        $discount = 0;

        if ($coupon !== null && $coupon->isActiveAt(Carbon::now()) && $subtotal >= $coupon->min_order_paise) {
            $discount = $this->coupons->discountFor($coupon, $subtotal);
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
            'coupon' => $coupon?->code,
        ];
    }

    private function cartFor(User $customer): ?Cart
    {
        return Cart::query()->with('coupon')->where('user_id', $customer->getKey())->first();
    }

    private function subtotalPaise(Cart $cart): int
    {
        return (int) $cart->items()
            ->with('productVariant')
            ->get()
            ->sum(fn (CartItem $item): int => $item->productVariant->price_paise * $item->quantity);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY_PER_LINE) {
            throw new InvalidArgumentException(__('Quantity must be between 1 and :max.', ['max' => self::MAX_QUANTITY_PER_LINE]));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertInStock(ProductVariant $variant, int $quantity): void
    {
        if ($quantity > $variant->stock_quantity) {
            throw new InvalidArgumentException(__('Only :count in stock.', ['count' => $variant->stock_quantity]));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertOwnsItem(User $customer, CartItem $item): void
    {
        if ($item->cart?->user_id !== $customer->getKey()) {
            throw new InvalidArgumentException(__('That item is not in this customer\'s cart.'));
        }
    }
}
