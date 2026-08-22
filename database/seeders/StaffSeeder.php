<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'gourmet-bistro-downtown')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $waiterRole = Role::where('slug', 'waiter')->first();

        if (!$restaurant) {
            $this->command->error('Run RestaurantAdminSeeder before StaffSeeder!');
            return;
        }

        // 1. Create Cashier
        $cashier = User::updateOrCreate(
            ['email' => 'cashier@restaurant.com'],
            [
                'name' => 'John Cashier',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_super_admin' => false,
            ]
        );

        if ($cashierRole) {
            $cashier->roles()->syncWithoutDetaching([
                $cashierRole->id => ['restaurant_id' => $restaurant->id],
            ]);
        }

        // 2. Create Waiter
        $waiter = User::updateOrCreate(
            ['email' => 'waiter@restaurant.com'],
            [
                'name' => 'Jane Waiter',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_super_admin' => false,
            ]
        );

        if ($waiterRole) {
            $waiter->roles()->syncWithoutDetaching([
                $waiterRole->id => ['restaurant_id' => $restaurant->id],
            ]);
        }

        $this->command->info('Staff seeded: cashier@restaurant.com & waiter@restaurant.com / password123');
    }
}