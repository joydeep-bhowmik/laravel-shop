<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(29900, 499900);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'product_variant_id' => null,
            'product_name' => ucwords(fake()->words(3, true)),
            'variant_name' => null,
            'sku' => strtoupper(fake()->bothify('SKU-????-####')),
            'unit_price_paise' => $unitPrice,
            'quantity' => $quantity,
            'line_total_paise' => $unitPrice * $quantity,
            'gst_rate' => 18,
            'tax_paise' => 0,
        ];
    }
}
