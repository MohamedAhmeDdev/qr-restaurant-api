<?php

namespace Database\Seeders;

use App\Models\Organizations;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RestaurantAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or retrieve the Restaurant Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@restaurant.com'],
            [
                'name' => 'Restaurant Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_super_admin' => false,
                'two_factor_enabled' => false,
            ]
        );

        // 2. Create an Organization owned by this user
        $organization = Organizations::updateOrCreate(
            ['slug' => 'gourmet-hospitality-group'],
            [
                'name' => 'Gourmet Hospitality Group',
                'owner_id' => $admin->id,
                'is_active' => true,
            ]
        );

        // 3. Create sample Restaurants under the Organization
        $restaurantMain = Restaurant::updateOrCreate(
            ['slug' => 'gourmet-bistro-downtown'],
            [
                'name' => 'Gourmet Bistro - Downtown',
                'organization_id' => $organization->id,
                'is_active' => true,
                'status' => 'active',
                'logo' => 'logos/bistro-downtown.png',
            ]
        );

        $restaurantBranch = Restaurant::updateOrCreate(
            ['slug' => 'gourmet-express-uptown'],
            [
                'name' => 'Gourmet Express - Uptown',
                'organization_id' => $organization->id,
                'is_active' => true,
                'status' => 'active',
                'logo' => 'logos/express-uptown.png',
            ]
        );

        // 4. Assign the 'restaurant_admin' role to the admin for both restaurants
        $roleAdmin = Role::where('slug', 'restaurant_admin')->first();

        if ($roleAdmin) {
            $admin->roles()->syncWithoutDetaching([
                $roleAdmin->id => ['restaurant_id' => $restaurantMain->id],
            ]);

            $admin->roles()->syncWithoutDetaching([
                $roleAdmin->id => ['restaurant_id' => $restaurantBranch->id],
            ]);
        }

        $this->command->info('Restaurant Admin created: admin@restaurant.com / password123');
    }
}