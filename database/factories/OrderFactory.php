<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(29900, 499900);

        return [
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'user_id' => User::factory(),
            'coupon_id' => null,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Razorpay,
            'coupon_code' => null,
            'shipping_method' => 'standard',
            'shipping_state' => 'Maharashtra',
            'shipping_address' => [
                'full_name' => fake()->name(),
                'phone' => '9'.fake()->numerify('#########'),
                'line1' => fake()->streetAddress(),
                'line2' => null,
                'landmark' => null,
                'city' => fake()->city(),
                'state' => 'Maharashtra',
                'pincode' => fake()->numerify('4#####'),
                'country' => 'IN',
            ],
            'subtotal_paise' => $subtotal,
            'discount_paise' => 0,
            'shipping_paise' => 0,
            'cgst_paise' => 0,
            'sgst_paise' => 0,
            'igst_paise' => 0,
            'total_paise' => $subtotal,
            'customer_note' => null,
            'placed_at' => now(),
        ];
    }

    /**
     * Mark the order as paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'payment_status' => PaymentStatus::Captured,
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark the order as delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Captured,
            'paid_at' => now()->subDays(5),
            'shipped_at' => now()->subDays(4),
            'delivered_at' => now(),
        ]);
    }
}
