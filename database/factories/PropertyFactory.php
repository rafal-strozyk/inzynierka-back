<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
// database/factories/PropertyFactory.php
namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => $this->faker->words(2, true),

            'street' => $this->faker->streetName(),
            'street_number' => (string) $this->faker->buildingNumber(),
            'apartment_number' => $this->faker->optional()->numberBetween(1, 200),
            'city' => $this->faker->city(),

            'description' => $this->faker->optional()->sentence(),

            'status' => $this->faker->randomElement(['wolna','zajęta','w_remoncie','nieaktywna']),

            'rent_cost' => $this->faker->randomFloat(2, 800, 5000),
            'utilities_cost' => $this->faker->randomFloat(2, 0, 1200),
            'additional_costs' => $this->faker->randomFloat(2, 0, 800),

            'area_total' => $this->faker->optional()->randomFloat(2, 15, 120),
            'bathrooms_count' => $this->faker->optional()->numberBetween(1, 3),

            'has_balcony' => $this->faker->boolean(),
            'rent_by_rooms' => $this->faker->boolean(),
        ];
    }
}

