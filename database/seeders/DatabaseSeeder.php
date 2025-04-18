<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\CompanyWallet;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed company wallets for each network
        $wallets = [
            ['network' => 'Binance Chain (BEP-20)', 'address' => '0x1e668ecf81123958bcb90ecea0501f2b883597c4'],
            ['network' => 'TRON (TRC-20)', 'address' => 'TUaNwA2sZjR4XRAYc5UnaqbkhseVoW122W'],
        ];

        foreach ($wallets as $wallet) {
            CompanyWallet::create($wallet);
        }

        $password = "password123";

        User::factory()->create([
            'email' => 'admin@gmail.com',
            'role' => 'admin',
            'first_name' => 'Tamba',
            'last_name' => 'Ganza',
            'phone_number' => '0788519633',
            'password' => Hash::make($password),
            'usdt_wallet' => 'TWallet123',
            'promocode' => 'ADMIN123',
        ]);
    }
}
