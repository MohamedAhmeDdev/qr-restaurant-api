<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin: Explicitly assign ONLY platform management permissions
        $superAdmin = Role::where('slug', 'super_admin')->first();
        if ($superAdmin) {
            $superAdminPermissions = Permission::whereIn('slug', [
                'permission.view',
                'permission.create',
                'permission.update',
                'permission.delete',
                'permission.assign',
                'role.view',
                'role.create',
                'role.update',
                'role.delete',
                'invitation.send',
                'organization.force_delete',
            ])->get();

            $superAdmin->permissions()->sync($superAdminPermissions->pluck('id'));
        }

        // 2. Restaurant Admin: Has access to all core restaurant operational groups
        $restaurantAdmin = Role::where('slug', 'restaurant_admin')->first();
        if ($restaurantAdmin) {
            $restaurantAdminPermissions = Permission::whereIn('group', [
                'organization',
                'restaurant',
                'category',
                'modifier',
                'menu',
                'order',
                'table',
                'staff',
                'report',
            ])->get();

            $restaurantAdmin->permissions()->sync($restaurantAdminPermissions->pluck('id'));
        }

        // 3. Manager: Operational permissions
        $manager = Role::where('slug', 'manager')->first();
        if ($manager) {
            $managerPermissions = Permission::whereIn('group', [
                'category',
                'modifier',
                'menu',
                'order',
                'table',
                'staff',
                'report',
            ])->get();

            $manager->permissions()->sync($managerPermissions->pluck('id'));
        }

        // 4. Cashier: Order processing, menu/table views, and sales reporting
        $cashier = Role::where('slug', 'cashier')->first();
        if ($cashier) {
            $cashierPermissions = Permission::whereIn('slug', [
                'order.view',
                'order.update_status',
                'order.cancel',
                'menu.view',
                'category.view',
                'modifier.view',
                'table.view',
                'report.view',
            ])->get();

            $cashier->permissions()->sync($cashierPermissions->pluck('id'));
        }

        // 5. Waiter: Basic table and order operations
        $waiter = Role::where('slug', 'waiter')->first();
        if ($waiter) {
            $waiterPermissions = Permission::whereIn('slug', [
                'order.view',
                'order.update_status',
                'menu.view',
                'category.view',
                'modifier.view',
                'table.view',
            ])->get();

            $waiter->permissions()->sync($waiterPermissions->pluck('id'));
        }
    }
}