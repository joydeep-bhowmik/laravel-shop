<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $value = ucfirst(fake()->unique()->word());

        return [
            'attribute_id' => Attribute::factory(),
            'value' => $value,
            'slug' => Str::slug($value),
            'color_hex' => null,
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
