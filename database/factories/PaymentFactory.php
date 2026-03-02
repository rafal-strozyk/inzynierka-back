<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 500, 9000);

        return [
            'contract_id' => Contract::factory(),
            'paid_by_user_id' => User::factory()->state(['role' => 'tenant']),
            'payment_number' => strtoupper(fake()->bothify('PAY-######')),
            'invoice_title' => fake()->optional()->sentence(3),
            'invoice_description' => fake()->optional()->sentence(),
            'amount' => $amount,
            'due_date' => fake()->dateTimeBetween('-2 months', '+2 months')->format('Y-m-d'),
            'status' => fake()->randomElement(['to_be_paid', 'in_progress', 'confirmed']),
            'paid_amount' => fake()->optional()->randomFloat(2, 0, $amount),
            'payment_date' => fake()->optional()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'paid_by' => fake()->randomElement(['cash', 'bank_transfer']),
        ];
    }
}
