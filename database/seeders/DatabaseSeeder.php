<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Delete all files and the folder itself
        Storage::disk('public')->deleteDirectory('restaurants');
        
        // Re-create the empty directory
        Storage::disk('public')->makeDirectory('restaurants');

        // Run custom seeders
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}