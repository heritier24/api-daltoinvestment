<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\CompanyWallet;
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
    }
}
