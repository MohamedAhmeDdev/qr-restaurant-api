<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Permission Management
            [
                'name' => 'View Permission',
                'slug' => 'permission.view',
                'group' => 'permission',
                'description' => 'View permission',
            ],
            [
                'name' => 'Create Permission',
                'slug' => 'permission.create',
                'group' => 'permission',
                'description' => 'Create permission',
            ],
            [
                'name' => 'Update Permission',
                'slug' => 'permission.update',
                'group' => 'permission',
                'description' => 'Update permission',
            ],
            [
                'name' => 'Delete Permission',
                'slug' => 'permission.delete',
                'group' => 'permission',
                'description' => 'Delete permission',
            ],
            [
                'name' => 'Assign Permission',
                'slug' => 'permission.assign',
                'group' => 'permission',
                'description' => 'Assign permission to role',
            ],

            // Role Management
            [
                'name' => 'View Role',
                'slug' => 'role.view',
                'group' => 'role',
                'description' => 'View role',
            ],
            [
                'name' => 'Create Role',
                'slug' => 'role.create',
                'group' => 'role',
                'description' => 'Create role',
            ],
            [
                'name' => 'Update Role',
                'slug' => 'role.update',
                'group' => 'role',
                'description' => 'Update role',
            ],
            [
                'name' => 'Delete Role',
                'slug' => 'role.delete',
                'group' => 'role',
                'description' => 'Delete role',
            ],

            // Invitation Management
            [
                'name' => 'Send Invitation',
                'slug' => 'invitation.send',
                'group' => 'invitation',
                'description' => 'Send invitation to user',
            ],

            // Restaurant Management
            [
                'name' => 'View Restaurant',
                'slug' => 'restaurant.view',
                'group' => 'restaurant',
                'description' => 'View restaurant information',
            ],
            [
                'name' => 'Create Restaurant',
                'slug' => 'restaurant.create',
                'group' => 'restaurant',
                'description' => 'Create restaurant information',
            ],
            [
                'name' => 'Update Restaurant',
                'slug' => 'restaurant.update',
                'group' => 'restaurant',
                'description' => 'Update restaurant information',
            ],
            [
                'name' => 'Delete Restaurant',
                'slug' => 'restaurant.delete',
                'group' => 'restaurant',
                'description' => 'Delete restaurant information',
            ],
            [
                'name' => 'Restore Restaurant',
                'slug' => 'restaurant.restore',
                'group' => 'restaurant',
                'description' => 'Restore restaurant information',
            ],
            [
                'name' => 'Force Delete Restaurant',
                'slug' => 'restaurant.force_delete',
                'group' => 'restaurant',
                'description' => 'Force delete restaurant information',
            ],

            // Staff Management
            [
                'name' => 'View Staff',
                'slug' => 'staff.view',
                'group' => 'staff',
                'description' => 'View restaurant staff',
            ],
            [
                'name' => 'Create Staff',
                'slug' => 'staff.create',
                'group' => 'staff',
                'description' => 'Create restaurant staff',
            ],
            [
                'name' => 'Update Staff',
                'slug' => 'staff.update',
                'group' => 'staff',
                'description' => 'Update restaurant staff',
            ],
            [
                'name' => 'Delete Staff',
                'slug' => 'staff.delete',
                'group' => 'staff',
                'description' => 'Delete restaurant staff',
            ],
            [
                'name' => 'Restore Staff',
                'slug' => 'staff.restore',
                'group' => 'staff',
                'description' => 'Restore restaurant staff',
            ],

            // Table Management
            [
                'name' => 'View Table',
                'slug' => 'table.view',
                'group' => 'table',
                'description' => 'View restaurant table',
            ],
            [
                'name' => 'Create Table',
                'slug' => 'table.create',
                'group' => 'table',
                'description' => 'Create restaurant table',
            ],
            [
                'name' => 'Update Table',
                'slug' => 'table.update',
                'group' => 'table',
                'description' => 'Update restaurant table',
            ],
            [
                'name' => 'Delete Table',
                'slug' => 'table.delete',
                'group' => 'table',
                'description' => 'Delete restaurant table',
            ],

            // Category Management
            [
                'name' => 'View Category',
                'slug' => 'category.view',
                'group' => 'category',
                'description' => 'View category',
            ],
            [
                'name' => 'Create Category',
                'slug' => 'category.create',
                'group' => 'category',
                'description' => 'Create category',
            ],
            [
                'name' => 'Update Category',
                'slug' => 'category.update',
                'group' => 'category',
                'description' => 'Update category',
            ],
            [
                'name' => 'Delete Category',
                'slug' => 'category.delete',
                'group' => 'category',
                'description' => 'Delete category',
            ],

            // Menu Management
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
                'description' => 'Create menu item',
            ],
            [
                'name' => 'Update Menu',
                'slug' => 'menu.update',
                'group' => 'menu',
                'description' => 'Update menu item',
            ],
            [
                'name' => 'Delete Menu',
                'slug' => 'menu.delete',
                'group' => 'menu',
                'description' => 'Delete menu item',
            ],

            // Order Management
            [
                'name' => 'View Order',
                'slug' => 'order.view',
                'group' => 'order',
                'description' => 'View order',
            ],
            [
                'name' => 'Create Order',
                'slug' => 'order.create',
                'group' => 'order',
                'description' => 'Create order',
            ],
            [
                'name' => 'Update Order',
                'slug' => 'order.update',
                'group' => 'order',
                'description' => 'Update order',
            ],
            [
                'name' => 'Cancel Order',
                'slug' => 'order.cancel',
                'group' => 'order',
                'description' => 'Cancel order',
            ],
            [
                'name' => 'Update Order Status',
                'slug' => 'order.update_status',
                'group' => 'order',
                'description' => 'Update order status',
            ],
            

            // Payment Management
            [
                'name' => 'View Payment',
                'slug' => 'payment.view',
                'group' => 'payment',
                'description' => 'View payment',
            ],
            [
                'name' => 'Process Payment',
                'slug' => 'payment.process',
                'group' => 'payment',
                'description' => 'Process payment',
            ],

            // Report Management
            [
                'name' => 'View Report',
                'slug' => 'report.view',
                'group' => 'report',
                'description' => 'View report',
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