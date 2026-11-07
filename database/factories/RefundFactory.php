<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payment = Payment::factory();

        return [
            'payment_id' => $payment,
            'order_id' => Order::factory(),
            'user_id' => null,
            'gateway_refund_id' => 'rfnd_'.Str::random(14),
            'amount_paise' => fake()->numberBetween(10000, 99900),
            'status' => RefundStatus::Processed,
            'reason' => fake()->sentence(5),
            'raw_payload' => null,
            'processed_at' => now(),
        ];
    }
}
