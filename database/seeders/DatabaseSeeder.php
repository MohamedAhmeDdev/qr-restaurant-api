<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

        // Run custom seeders
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
           SuperAdminSeeder::class,

        ]);
    }
}