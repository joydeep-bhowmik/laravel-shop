<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Work', 'Parents']),
            'full_name' => fake()->name(),
            'phone' => '9'.fake()->numerify('#########'),
            'line1' => fake()->buildingNumber().', '.fake()->streetName(),
            'line2' => null,
            'landmark' => fake()->boolean(30) ? 'Near '.fake()->citySuffix() : null,
            'city' => fake()->city(),
            'state' => 'Maharashtra',
            'pincode' => fake()->numerify('4#####'),
            'country' => 'IN',
            'is_default' => false,
        ];
    }

    /**
     * Mark the address as the customer's default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
