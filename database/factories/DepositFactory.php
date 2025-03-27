<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deposit>
 */
class DepositFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed']),
            'network' => $this->faker->randomElement(['TRON', 'Ethereum', 'Binance Smart Chain']),
            'reference_number' => $this->faker->boolean(80) ? 'REF' . $this->faker->numberBetween(100, 999) : 'N/A',
        ];
    }
}
