<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\CompanyWallet;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed company wallets for each network
        $wallets = [
            ['network' => 'BSC', 'address' => 'BSC9876zyxw5432vuts1098'],
            ['network' => 'TRC20', 'address' => 'TRC20klmn5678opqr9012abcd'],
        ];

        foreach ($wallets as $wallet) {
            CompanyWallet::create($wallet);
        }

        User::factory()->create([
            'email' => 'admin@gmail.com',
            'role' => 'admin',
            'first_name' => 'Tamba',
            'last_name' => 'Ganza',
            'phone_number' => '0788519633',
            'usdt_wallet' => 'TWallet123',
            'promocode' => 'ADMIN123',
        ]);

        // Create deposits for each user
        User::all()->each(function ($user) {
            Deposit::factory()->create([
                'user_id' => $user->id,
                'status' => fake()->randomElement(['pending', 'completed', 'failed']),
                'network' => fake()->randomElement(['TRON', 'Ethereum', 'Binance Smart Chain']),
                'reference_number' => fake()->boolean(80) ? 'REF' . fake()->numberBetween(100, 999) : 'N/A',
                'amount' => fake()->randomFloat(2, 100, 5000),
            ]);
        });

        // Create transactions for each user
        User::all()->each(function ($user) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'type' => fake()->randomElement(['deposit', 'withdrawal']),
                'status' => fake()->randomElement(['pending', 'completed', 'failed']),
                'network' => fake()->randomElement(['BSC', 'TRC20']),
                'reference_number' => fake()->boolean(80) ? 'REF' . fake()->numberBetween(100, 999) : 'N/A',
                'amount' => '0',
                'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            ]);
        });

        // Create withdrawals for each user
        User::all()->each(function ($user) {
            Withdrawal::factory()->create([
                'user_id' => $user->id,
                'status' => fake()->randomElement(['pending', 'completed', 'failed']),
                'network' => fake()->randomElement(['TRON', 'Ethereum', 'Binance Smart Chain']),
                'amount' => fake()->randomFloat(2, 100, 5000),
            ]);
        });
    }
}
