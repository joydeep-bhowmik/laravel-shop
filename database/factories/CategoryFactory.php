<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => ucwords(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * Nest the category under the given parent.
     */
    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => ['parent_id' => $parent->id]);
    }
}
