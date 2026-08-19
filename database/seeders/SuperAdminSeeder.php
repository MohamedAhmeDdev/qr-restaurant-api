<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@platform.com'],
            [
                'name' => 'Platform Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_super_admin' => true,
            ]
        );
        
        $this->command->info('Super Admin created: superadmin@platform.com / password');
    }
}