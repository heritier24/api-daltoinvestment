<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyWallet>
 */
class CompanyWalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'network' => $this->faker->unique()->randomElement(['BSC', 'TRC20']),
            'address' => $this->faker->uuid,
        ];
    }
}
