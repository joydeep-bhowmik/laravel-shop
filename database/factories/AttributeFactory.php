<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'is_variant' => false,
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Mark the attribute as a variant option such as size or colour.
     */
    public function variant(): static
    {
        return $this->state(fn (array $attributes) => ['is_variant' => true]);
    }
}
