<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [

            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Full platform access',
                'is_system' => true,
            ],

            [
                'name' => 'Restaurant Admin',
                'slug' => 'restaurant_admin',
                'description' => 'Full access to a restaurant',
                'is_system' => true,
            ],

            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Manage restaurant operations',
                'is_system' => true,
            ],

            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Restaurant operational staff',
                'is_system' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}