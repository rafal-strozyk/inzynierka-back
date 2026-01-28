<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\TenantProperty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantPropertyFactory extends Factory
{
    protected $model = TenantProperty::class;

    public function definition(): array
    {
        return [
            'tenant_id' => User::factory()->state([
                'role' => 'tenant',
            ]),
            'property_id' => Property::factory(),
            'room_id' => null,
            'start_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'end_date' => null,
            'is_active' => true,
        ];
    }
}
