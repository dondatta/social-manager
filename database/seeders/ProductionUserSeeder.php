<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Change these details to what you want!
        $email = 'admin@social.chrismichaelsmgmt.com';
        $password = 'password123'; // CHANGE THIS IMMEDIATELY AFTER LOGIN
        $name = 'Admin';

        if (!User::where('email', $email)->exists()) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $this->command->info("User {$email} created successfully.");
        } else {
            $this->command->warn("User {$email} already exists.");
        }
    }
}




