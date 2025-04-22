<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:admin-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a default admin user with interactive input';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating a new admin user...');

        // Prompt for user input
        $email = $this->ask('Enter email address', 'admin@gmail.com');
        $role = $this->ask('Enter role', 'admin');
        $firstName = $this->ask('Enter first name', 'Tamba');
        $lastName = $this->ask('Enter last name', 'Ganza');
        $phoneNumber = $this->ask('Enter phone number', '0788519633');
        $password = $this->ask('Enter password', 'password123');
        $usdtWallet = $this->ask('Enter USDT wallet', 'TWallet123');
        $promocode = $this->ask('Enter promocode', 'ADMIN123');

        // Create the user
        try {
            User::create([
                'email' => $email,
                'role' => $role,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => $phoneNumber,
                'password' => Hash::make($password),
                'usdt_wallet' => $usdtWallet,
                'promocode' => $promocode,
            ]);

            $this->info('Admin user created successfully!');
            $this->info("Email: $email");
            $this->info("Role: $role");
            $this->info("Name: $firstName $lastName");
            $this->info("Phone: $phoneNumber");
            $this->info("USDT Wallet: $usdtWallet");
            $this->info("Promocode: $promocode");
            $this->info("password: $password");
        } catch (\Exception $e) {
            $this->error('Failed to create admin user: ' . $e->getMessage());
        }
    }
}
