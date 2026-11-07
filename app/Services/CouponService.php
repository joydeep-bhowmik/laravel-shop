<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CouponService
{
    /**
     * Check a coupon code against the rules for this customer and order subtotal.
     *
     * @throws InvalidArgumentException With a message the customer can read when the code cannot be used.
     */
    public function validate(string $code, User $user, int $subtotalPaise, ?Carbon $now = null): Coupon
    {
        $now ??= Carbon::now();

        $coupon = Coupon::query()->code($code)->first();

        if ($coupon === null) {
            throw new InvalidArgumentException(__('That coupon code does not exist.'));
        }

        if (! $coupon->isActiveAt($now)) {
            throw new InvalidArgumentException(__('This coupon is not active right now.'));
        }

        if ($subtotalPaise < $coupon->min_order_paise) {
            throw new InvalidArgumentException(__('This coupon needs an order of at least :amount.', [
                'amount' => MoneyFormatter::format($coupon->min_order_paise),
            ]));
        }

        if ($coupon->hasReachedUsageLimit()) {
            throw new InvalidArgumentException(__('This coupon has been used up.'));
        }

        if ($coupon->per_user_limit !== null
            && CouponUsage::query()->where('coupon_id', $coupon->id)->where('user_id', $user->id)->count() >= $coupon->per_user_limit) {
            throw new InvalidArgumentException(__('You have already used this coupon.'));
        }

        return $coupon;
    }

    /**
     * The discount in paise this coupon gives on a subtotal. Never more than the subtotal.
     */
    public function discountFor(Coupon $coupon, int $subtotalPaise): int
    {
        $discount = match ($coupon->type) {
            CouponType::Percentage => intdiv($subtotalPaise * $coupon->value, 100),
            CouponType::Fixed => $coupon->value,
        };

        if ($coupon->type === CouponType::Percentage && $coupon->max_discount_paise !== null) {
            $discount = min($discount, $coupon->max_discount_paise);
        }

        return max(0, min($discount, $subtotalPaise));
    }

    /**
     * Record that an order used this coupon and bump its usage count. Call inside the order placement transaction.
     *
     * @throws InvalidArgumentException When the usage limit was reached in the meantime.
     */
    public function recordUsage(Coupon $coupon, User $user, Order $order): void
    {
        DB::transaction(function () use ($coupon, $user, $order): void {
            $locked = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->hasReachedUsageLimit()) {
                throw new InvalidArgumentException(__('This coupon has been used up.'));
            }

            $locked->increment('usage_count');

            CouponUsage::query()->create([
                'coupon_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'order_id' => $order->getKey(),
            ]);
        });
    }
}
