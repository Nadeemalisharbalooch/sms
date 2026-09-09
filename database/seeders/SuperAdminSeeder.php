<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'superadmin@sms.com';
        $password = 'password';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_admin' => true,
                'is_institute' => false,
                'is_active' => true,
                'phone' => '+923001234567',
            ]
        );

        $this->command?->info("Super Admin created: {$email} / {$password}");
    }
}
