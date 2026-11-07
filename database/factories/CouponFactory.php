<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(6),
            'type' => CouponType::Percentage,
            'value' => 10,
            'max_discount_paise' => null,
            'min_order_paise' => 0,
            'usage_limit' => null,
            'usage_count' => 0,
            'per_user_limit' => 1,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * Make the coupon a fixed rupee amount off, given in paise.
     */
    public function fixed(int $paise): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponType::Fixed,
            'value' => $paise,
        ]);
    }

    /**
     * Make the coupon expire in the past.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }
}
