<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => $name,
            'type' => ProductType::Simple,
            'status' => ProductStatus::Published,
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'meta_title' => $name,
            'meta_description' => fake()->sentence(18),
            'hsn_code' => fake()->numerify('61####'),
            'gst_rate' => fake()->randomElement([5, 12, 18]),
            'published_at' => Carbon::now()->subDays(fake()->numberBetween(1, 120)),
            'scheduled_for' => null,
            'deal_ends_at' => null,
        ];
    }

    /**
     * Keep the product in draft so it stays hidden from the storefront.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Schedule the product to go live at a future time.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Scheduled,
            'published_at' => null,
            'scheduled_for' => Carbon::now()->addDays(fake()->numberBetween(1, 14)),
        ]);
    }

    /**
     * Give the product a variable type, meant to be paired with several variants.
     */
    public function variable(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ProductType::Variable]);
    }

    /**
     * Put the product on a deal with a countdown.
     */
    public function onDeal(): static
    {
        return $this->state(fn (array $attributes) => [
            'deal_ends_at' => Carbon::now()->addHours(fake()->numberBetween(6, 96)),
        ]);
    }
}
