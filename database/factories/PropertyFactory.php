<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    private const PROPERTY_TYPES = ['room', 'flat'];

    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory()->state(['role' => 'owner']),
            'name' => fake()->words(2, true),
            'street' => fake()->streetName(),
            'street_number' => (string) fake()->buildingNumber(),
            'apartment_number' => fake()->optional()->numberBetween(1, 200),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'area' => fake()->randomFloat(2, 12, 180),
            'rooms_count' => fake()->numberBetween(1, 8),
            'bathrooms_count' => fake()->numberBetween(1, 3),
            'has_balcony' => fake()->boolean(),
            'rent_cost' => fake()->randomFloat(2, 900, 9000),
            'utilities_cost' => fake()->randomFloat(2, 100, 2000),
            'additional_costs' => fake()->randomFloat(2, 0, 1000),
            'type' => fake()->randomElement(self::PROPERTY_TYPES),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
