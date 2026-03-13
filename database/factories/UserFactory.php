<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    private const ROLE_ADMIN = 'admin';

    private const ROLE_OWNER = 'owner';

    private const ROLE_TENANT = 'tenant';

    private const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_OWNER,
        self::ROLE_TENANT,
    ];

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => fake()->randomElement(self::ROLES),
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->streetAddress(),
            'postal_code' => fake()->optional()->postcode(),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'pesel' => fake()->unique()->numerify('###########'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
