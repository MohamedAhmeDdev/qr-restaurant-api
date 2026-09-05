<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Organizations; // Adjust model name if needed
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Create or retrieve testing User for owner_id
        $owner = User::firstOrCreate(
            ['email' => 'owner@test.com'],
            [
                'name'     => 'Test Owner',
                'password' => Hash::make('password'),
            ]
        );

        // 1. Create or retrieve testing Organization with owner_id
        $organization = Organizations::firstOrCreate(
            ['slug' => 'test-organization'],
            [
                'name'     => 'Test Organization',
                'owner_id' => $owner->id,
            ]
        );

        // 2. Create or retrieve testing Restaurant and Table
        $restaurant = Restaurant::firstOrCreate(
            ['slug' => 'test-bistro'],
            [
                'organization_id' => $organization->id,
                'name'            => 'Test Bistro',
            ]
        );

        $table = Table::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => 'table-1'],
            [
                'name'      => 'Table 1',
                'token'     => Str::random(16),
                'is_active' => true,
            ]
        );

        // 3. Create Category and Menu Items
        $category = Category::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Mains'],
            ['slug' => 'mains', 'sort_order' => 1, 'is_active' => true]
        );

        $burger = MenuItem::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Classic Cheeseburger'],
            [
                'category_id'  => $category->id,
                'slug'         => 'classic-cheeseburger',
                'description'  => 'Angus beef patty with sharp cheddar.',
                'price'        => 12.50,
                'is_available' => true,
            ]
        );

        // 4. Create Modifier Group & Options
        $modGroup = ModifierGroup::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Extra Toppings'],
            ['min_select' => 0, 'max_select' => 3, 'is_required' => true]
        );

        $bacon = ModifierOption::firstOrCreate(
            ['modifier_group_id' => $modGroup->id, 'name' => 'Bacon'],
            ['price' => 2.00, 'is_available' => true]
        );

        $cheese = ModifierOption::firstOrCreate(
            ['modifier_group_id' => $modGroup->id, 'name' => 'Extra Cheese'],
            ['price' => 1.50, 'is_available' => true]
        );

        // Attach modifier group to item if not already attached
        if (! $burger->modifierGroups()->where('modifier_group_id', $modGroup->id)->exists()) {
            $burger->modifierGroups()->attach($modGroup->id);
        }

        // 5. Generate Sample Orders with Valid Database Statuses
        // Note: Using 'received' and 'delivered' to satisfy orders_status_check
 $statuses = ['pending', 'preparing', 'ready', 'cancelled'];

        foreach ($statuses as $index => $status) {
            $quantity = 2;
            $unitPrice = (float) $burger->price;
            $modifierTotal = (float) $bacon->price + (float) $cheese->price;
            $itemSubtotal = ($unitPrice + $modifierTotal) * $quantity;

            $order = Order::create([
                'restaurant_id'  => $restaurant->id,
                'table_id'       => $table->id,
                'order_number'   => 'ORD-' . strtoupper(Str::random(6)),
                'status'         => $status,
                'payment_status' => $status === 'delivered' ? 'paid' : 'pending',
                'type'           => 'dine_in',
                'notes'          => "Test order for {$status} status.",
                'subtotal'       => $itemSubtotal,
                'total_amount'   => $itemSubtotal,
                'created_at'     => now()->subMinutes(($index + 1) * 15),
            ]);

            // Add Order Item
            $orderItem = OrderItem::create([
                'order_id'             => $order->id,
                'menu_item_id'         => $burger->id,
                'item_name'            => $burger->name,
                'quantity'             => $quantity,
                'unit_price'           => $unitPrice,
                'subtotal'             => $itemSubtotal,
                'special_instructions' => 'Medium rare, extra crispy bacon.',
            ]);

            // Add Item Modifiers
            OrderItemModifier::create([
                'order_item_id'        => $orderItem->id,
                'modifier_group_name'  => $modGroup->name,
                'modifier_option_name' => $bacon->name,
                'unit_price'           => $bacon->price,
            ]);

            OrderItemModifier::create([
                'order_item_id'        => $orderItem->id,
                'modifier_group_name'  => $modGroup->name,
                'modifier_option_name' => $cheese->name,
                'unit_price'           => $cheese->price,
            ]);
        }
    }
}