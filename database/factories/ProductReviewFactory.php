<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'is_approved' => true,
        ];
    }

    /**
     * Keep the review hidden until a moderator approves it.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['is_approved' => false]);
    }
}
