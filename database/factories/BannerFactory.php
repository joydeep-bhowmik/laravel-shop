<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => ucwords(fake()->words(4, true)),
            'subtitle' => fake()->sentence(8),
            'cta_label' => 'Shop now',
            'cta_url' => '/shop',
            'position' => fake()->numberBetween(0, 5),
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
