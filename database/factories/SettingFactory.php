<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'general',
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'is_encrypted' => false,
        ];
    }

    /**
     * Store the value encrypted at rest.
     */
    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => ['is_encrypted' => true]);
    }
}
