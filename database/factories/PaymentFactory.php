<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'razorpay',
            'method' => 'upi',
            'status' => PaymentStatus::Pending,
            'gateway_order_id' => 'order_'.Str::random(14),
            'gateway_payment_id' => null,
            'gateway_signature' => null,
            'amount_paise' => fake()->numberBetween(29900, 499900),
            'currency' => 'INR',
            'failure_reason' => null,
            'raw_payload' => null,
        ];
    }

    /**
     * Mark the payment as captured with a gateway payment id.
     */
    public function captured(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Captured,
            'gateway_payment_id' => 'pay_'.Str::random(14),
        ]);
    }

    /**
     * Cash on delivery payments are recorded without any gateway identifiers.
     */
    public function cashOnDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'cod',
            'method' => 'cod',
            'gateway_order_id' => null,
            'status' => PaymentStatus::Pending,
        ]);
    }
}
