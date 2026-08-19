<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::pluck('id');

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        $superAdmin = Role::where(
            'slug',
            'super_admin'
        )->firstOrFail();

        $superAdmin->permissions()->sync(
            $allPermissions
        );

        /*
        |--------------------------------------------------------------------------
        | Restaurant Admin
        |--------------------------------------------------------------------------
        */

        $restaurantAdmin = Role::where(
            'slug',
            'restaurant_admin'
        )->firstOrFail();

        $restaurantAdmin->permissions()->sync(
            Permission::whereIn('slug', [

                'dashboard.view',

                'restaurants.view',
                'restaurants.update',

                'users.view',
                'users.create',
                'users.update',
                'users.delete',

                'roles.view',
                'roles.create',
                'roles.update',
                'roles.delete',

                'permissions.view',

                'tables.view',
                'tables.create',
                'tables.update',
                'tables.delete',

                'menu.view',
                'menu.create',
                'menu.update',
                'menu.delete',

                'orders.view',
                'orders.create',
                'orders.update',
                'orders.cancel',

                'payments.view',
                'payments.process',

                'reports.view',

            ])->pluck('id')
        );

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        $manager = Role::where(
            'slug',
            'manager'
        )->firstOrFail();

        $manager->permissions()->sync(
            Permission::whereIn('slug', [

                'dashboard.view',

                'users.view',

                'tables.view',
                'tables.create',
                'tables.update',

                'menu.view',
                'menu.create',
                'menu.update',

                'orders.view',
                'orders.create',
                'orders.update',
                'orders.cancel',

                'payments.view',

                'reports.view',

            ])->pluck('id')
        );

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        */

        $staff = Role::where(
            'slug',
            'staff'
        )->firstOrFail();

        $staff->permissions()->sync(
            Permission::whereIn('slug', [

                'dashboard.view',

                'tables.view',

                'menu.view',

                'orders.view',
                'orders.create',
                'orders.update',

            ])->pluck('id')
        );
    }
}