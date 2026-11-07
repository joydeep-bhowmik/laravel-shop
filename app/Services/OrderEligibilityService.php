<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use InvalidArgumentException;

/**
 * Decides whether a signed-in customer may place an order, based on store settings.
 *
 * Checkout calls this before it creates an order. Guests are handled by checkout itself.
 */
class OrderEligibilityService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @throws InvalidArgumentException With a message the customer can read when ordering is refused.
     */
    public function ensureCanOrder(User $user): void
    {
        if ($this->settings->bool('checkout.require_verified_email') && $user->email_verified_at === null) {
            throw new InvalidArgumentException(__('Verify your email address before placing an order.'));
        }
    }

    /**
     * Refuse cash on delivery when any product in the order has it switched off.
     *
     * @param  iterable<int, Product>  $products
     *
     * @throws InvalidArgumentException
     */
    public function ensureCodAllowed(iterable $products): void
    {
        foreach ($products as $product) {
            if (! $product->cod_eligible) {
                throw new InvalidArgumentException(__('Cash on delivery is not available for :product.', ['product' => $product->name]));
            }
        }
    }
}
