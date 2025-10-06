<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'commission_fee' => $this->faker->randomFloat(2, 0, 10),
            'metadata' => ['note' => $this->faker->sentence()],
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
        ];
    }
}
