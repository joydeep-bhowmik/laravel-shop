<?php

namespace Database\Factories;

use App\Models\Pincode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pincode>
 */
class PincodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('######'),
            'city' => fake()->city(),
            'state' => 'Maharashtra',
            'is_serviceable' => true,
            'cod_available' => true,
            'delivery_days' => fake()->numberBetween(2, 7),
        ];
    }

    /**
     * Mark the pincode as outside the delivery network.
     */
    public function unserviceable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_serviceable' => false,
            'cod_available' => false,
        ]);
    }
}
