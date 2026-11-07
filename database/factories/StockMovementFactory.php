<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $change = fake()->numberBetween(1, 50);

        return [
            'product_variant_id' => ProductVariant::factory(),
            'order_id' => null,
            'user_id' => null,
            'quantity_change' => $change,
            'quantity_after' => $change,
            'reason' => StockMovementReason::Restock,
            'note' => null,
        ];
    }
}
