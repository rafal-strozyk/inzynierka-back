<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $property = Property::factory()->create();

        return [
            'property_id' => $property->id,
            'properties_name' => $property->name,
            'contract_number' => strtoupper(fake()->bothify('CTR-####??')),
            'start_date' => fake()->dateTimeBetween('-12 months', 'now')->format('Y-m-d'),
            'end_date' => fake()->optional()->dateTimeBetween('now', '+24 months')->format('Y-m-d'),
            'monthly_rent' => fake()->randomFloat(2, 900, 9000),
            'deposit' => fake()->randomFloat(2, 0, 10000),
            'status' => fake()->randomElement(['draft', 'active', 'terminated', 'expired']),
            'path' => fake()->optional()->filePath(),
            'filename' => fake()->optional()->word() . '.pdf',
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer']),
        ];
    }
}
