<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure the Super Admin role exists
        $role = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Platform owner with full system access',
            ]
        );

        // 2. Create or update the Super Admin user
        $user = User::updateOrCreate(
            ['email' => 'superadmin@platform.com'],
            [
                'name' => 'Platform Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // 3. Attach role with pivot column data
        $user->roles()->syncWithoutDetaching([
            $role->id => ['status' => 'active']
        ]);

        $this->command->info('Super Admin created: superadmin@platform.com / password123');
    }
}