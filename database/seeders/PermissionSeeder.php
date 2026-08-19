<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            // Dashboard
            [
                'name' => 'View Dashboard',
                'slug' => 'dashboard.view',
                'group' => 'dashboard',
                'description' => 'View the restaurant dashboard',
            ],

            // Restaurants
            [
                'name' => 'View Restaurant',
                'slug' => 'restaurants.view',
                'group' => 'restaurants',
                'description' => 'View restaurant information',
            ],
            [
                'name' => 'Update Restaurant',
                'slug' => 'restaurants.update',
                'group' => 'restaurants',
                'description' => 'Update restaurant information',
            ],

            // Users
            [
                'name' => 'View Users',
                'slug' => 'users.view',
                'group' => 'users',
                'description' => 'View restaurant users',
            ],
            [
                'name' => 'Create Users',
                'slug' => 'users.create',
                'group' => 'users',
                'description' => 'Create restaurant users',
            ],
            [
                'name' => 'Update Users',
                'slug' => 'users.update',
                'group' => 'users',
                'description' => 'Update restaurant users',
            ],
            [
                'name' => 'Delete Users',
                'slug' => 'users.delete',
                'group' => 'users',
                'description' => 'Delete restaurant users',
            ],

            // Roles
            [
                'name' => 'View Roles',
                'slug' => 'roles.view',
                'group' => 'roles',
                'description' => 'View roles',
            ],
            [
                'name' => 'Create Roles',
                'slug' => 'roles.create',
                'group' => 'roles',
                'description' => 'Create roles',
            ],
            [
                'name' => 'Update Roles',
                'slug' => 'roles.update',
                'group' => 'roles',
                'description' => 'Update roles',
            ],
            [
                'name' => 'Delete Roles',
                'slug' => 'roles.delete',
                'group' => 'roles',
                'description' => 'Delete roles',
            ],

            // Permissions
            [
                'name' => 'View Permissions',
                'slug' => 'permissions.view',
                'group' => 'permissions',
                'description' => 'View permissions',
            ],

            // Tables
            [
                'name' => 'View Tables',
                'slug' => 'tables.view',
                'group' => 'tables',
                'description' => 'View restaurant tables',
            ],
            [
                'name' => 'Create Tables',
                'slug' => 'tables.create',
                'group' => 'tables',
                'description' => 'Create restaurant tables',
            ],
            [
                'name' => 'Update Tables',
                'slug' => 'tables.update',
                'group' => 'tables',
                'description' => 'Update restaurant tables',
            ],
            [
                'name' => 'Delete Tables',
                'slug' => 'tables.delete',
                'group' => 'tables',
                'description' => 'Delete restaurant tables',
            ],

            // Menu
            [
                'name' => 'View Menu',
                'slug' => 'menu.view',
                'group' => 'menu',
                'description' => 'View menu',
            ],
            [
                'name' => 'Create Menu',
                'slug' => 'menu.create',
                'group' => 'menu',
                'description' => 'Create menu items',
            ],
            [
                'name' => 'Update Menu',
                'slug' => 'menu.update',
                'group' => 'menu',
                'description' => 'Update menu items',
            ],
            [
                'name' => 'Delete Menu',
                'slug' => 'menu.delete',
                'group' => 'menu',
                'description' => 'Delete menu items',
            ],

            // Orders
            [
                'name' => 'View Orders',
                'slug' => 'orders.view',
                'group' => 'orders',
                'description' => 'View orders',
            ],
            [
                'name' => 'Create Orders',
                'slug' => 'orders.create',
                'group' => 'orders',
                'description' => 'Create orders',
            ],
            [
                'name' => 'Update Orders',
                'slug' => 'orders.update',
                'group' => 'orders',
                'description' => 'Update orders',
            ],
            [
                'name' => 'Cancel Orders',
                'slug' => 'orders.cancel',
                'group' => 'orders',
                'description' => 'Cancel orders',
            ],
              [
                'name' => 'Update Order Status',
                'slug' => 'orders.update_status',
                'group' => 'orders',
                'description' => 'Update orders status.',
            ],

            // Payments
            [
                'name' => 'View Payments',
                'slug' => 'payments.view',
                'group' => 'payments',
                'description' => 'View payments',
            ],
            [
                'name' => 'Process Payments',
                'slug' => 'payments.process',
                'group' => 'payments',
                'description' => 'Process payments',
            ],

            // Reports
            [
                'name' => 'View Reports',
                'slug' => 'reports.view',
                'group' => 'reports',
                'description' => 'View reports',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}