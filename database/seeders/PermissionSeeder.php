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

            //organization management
            [
                'name' => 'View Organization',
                'slug' => 'organization.view',
                'group' => 'organization',
                'description' => 'View organization information',
            ],
            [
                'name' => 'Create Organization',
                'slug' => 'organization.create',
                'group' => 'organization',
                'description' => 'Create organization information',
            ],
            [
                'name' => 'Update Organization',
                'slug' => 'organization.update',
                'group' => 'organization',
                'description' => 'Update organization information',
            ],
            [
                'name' => 'Delete Organization',
                'slug' => 'organization.delete',
                'group' => 'organization',
                'description' => 'Delete organization information',
            ],
            [
                'name' => 'Restore Organization',
                'slug' => 'organization.restore',
                'group' => 'organization',
                'description' => 'Restore organization information',
            ],
            [
                'name' => 'Force Delete Organization',
                'slug' => 'organization.force_delete',
                'group' => 'organization',
                'description' => 'Force delete organization information',
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
            [
                'name' => 'Force Delete Staff',
                'slug' => 'staff.force_delete',
                'group' => 'staff',
                'description' => 'Force delete restaurant staff',
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
            [
                'name' => 'Restore Table',
                'slug' => 'table.restore',
                'group' => 'table',
                'description' => 'Restore restaurant table',
            ],
            [
                'name' => 'Force Delete Table',
                'slug' => 'table.force_delete',
                'group' => 'table',
                'description' => 'Force delete restaurant table',
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
            [
                'name' => 'Restore Category',
                'slug' => 'category.restore',
                'group' => 'category',
                'description' => 'Restore category',
            ],
            [
                'name' => 'Force Delete Category',
                'slug' => 'category.force_delete',
                'group' => 'category',
                'description' => 'Force delete category',
            ],

            //modifiers
            [
                'name' => 'View Modifier',
                'slug' => 'modifier.view',
                'group' => 'modifier',
                'description' => 'View modifier',
            ],
            [
                'name' => 'Create Modifier',
                'slug' => 'modifier.create',
                'group' => 'modifier',
                'description' => 'Create modifier',
            ],
            [
                'name' => 'Update Modifier',
                'slug' => 'modifier.update',
                'group' => 'modifier',
                'description' => 'Update modifier',
            ],
            [
                'name' => 'Delete Modifier',
                'slug' => 'modifier.delete',
                'group' => 'modifier',
                'description' => 'Delete modifier',
            ],
            [
                'name' => 'Restore Modifier',
                'slug' => 'modifier.restore',
                'group' => 'modifier',
                'description' => 'Restore modifier',
            ],
            [
                'name' => 'Force Delete Modifier',
                'slug' => 'modifier.force_delete',
                'group' => 'modifier',
                'description' => 'Force delete modifier',
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
            [
                'name' => 'Restore Menu',
                'slug' => 'menu.restore',
                'group' => 'menu',
                'description' => 'Restore menu item',
            ],
            [
                'name' => 'Force Delete Menu',
                'slug' => 'menu.force_delete',
                'group' => 'menu',
                'description' => 'Force delete menu item',
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
            [
                'name' => 'Restore Order',
                'slug' => 'order.restore',
                'group' => 'order',
                'description' => 'Restore order',
            ],
            [
                'name' => 'Force Delete Order',
                'slug' => 'order.force_delete',
                'group' => 'order',
                'description' => 'Force delete order',
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