<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(29900, 999900);

        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-????-####')),
            'name' => null,
            'price_paise' => $price,
            'compare_at_price_paise' => fake()->boolean(40) ? (int) ($price * 1.2) : null,
            'stock_quantity' => fake()->numberBetween(0, 120),
            'low_stock_threshold' => 5,
            'weight_grams' => fake()->numberBetween(100, 1500),
            'is_default' => false,
            'is_active' => true,
            'position' => 0,
        ];
    }

    /**
     * Mark this variant as the product's default selection.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    /**
     * Stock the variant at zero so it shows as out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock_quantity' => 0]);
    }
}
