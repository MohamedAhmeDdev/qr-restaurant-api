<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Safe creation: skips if test@example.com already exists
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Run custom seeders
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
           SuperAdminSeeder::class,

        ]);
    }
}