<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'razorpay',
            'event_id' => 'evt_'.Str::random(20),
            'event' => 'payment.captured',
            'payload' => ['event' => 'payment.captured'],
            'processed_at' => now(),
        ];
    }
}
