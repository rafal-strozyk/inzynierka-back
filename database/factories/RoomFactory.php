<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => $this->faker->words(2, true),
            'room_number' => $this->faker->optional()->bothify('##?'),
            'area' => $this->faker->optional()->randomFloat(2, 6, 35),
            'rent_cost' => $this->faker->randomFloat(2, 300, 2000),
            'status' => 'wolny',
        ];
    }
}
