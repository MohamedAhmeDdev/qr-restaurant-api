<?php

namespace App\Http\Controllers\Api\public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\StoreGuestOrderRequest;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestOrderController extends Controller
{
    /**
     * Get the current active order for the scanned table.
     */
public function current(Request $request): JsonResponse
{
    $restaurant = $request->attributes->get('restaurant');
    $table = $request->attributes->get('table');

    $order = Order::where('restaurant_id', $restaurant->id) // Added for security
        ->where('table_id', $table->id)
        ->whereIn('status', ['pending', 'preparing', 'ready'])
        ->with(['items.modifiers', 'table:id,name,slug'])
        ->latest()
        ->first();

    if (! $order) {
        return response()->json([
            'status'  => 'success',
            'message' => 'No active order found for this table.',
            'data'    => null,
        ]);
    }

    return response()->json([
        'status' => 'success',
        'data'   => $order,
    ]);
}

    public function store(StoreGuestOrderRequest $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');
        $table = $request->attributes->get('table');

        $validated = $request->validated();
        $totals = $request->getVerifiedTotals();

        $order = DB::transaction(function () use ($restaurant, $table, $validated, $totals) {
            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'table_id'      => $table->id,
                'order_number'  => $this->generateOrderNumber(),
                'status'        => 'pending',
                'notes'         => $validated['notes'] ?? null,
                'subtotal'      => $totals['subtotal'],
                'total_amount'  => $totals['total_amount'],
            ]);

            foreach ($validated['items'] as $itemData) {
                $menuItem = MenuItem::where('restaurant_id', $restaurant->id)
                    ->findOrFail($itemData['menu_item_id']);

                $basePrice = (float) $menuItem->price;
                $modifierTotal = 0.00;

                $orderItem = OrderItem::create([
                    'order_id'             => $order->id,
                    'menu_item_id'         => $menuItem->id,
                    'item_name'            => $menuItem->name,
                    'quantity'             => $itemData['quantity'],
                    'unit_price'           => $basePrice,
                    'subtotal'             => 0.00,
                    'special_instructions' => $itemData['special_instructions'] ?? null,
                ]);

                if (! empty($itemData['modifiers'])) {
                    foreach ($itemData['modifiers'] as $modData) {
                        $option = ModifierOption::whereHas('modifierGroup', function ($q) use ($restaurant) {
                            $q->where('restaurant_id', $restaurant->id);
                        })->findOrFail($modData['modifier_option_id']);

                        $modPrice = (float) $option->price;

                        OrderItemModifier::create([
                            'order_item_id'        => $orderItem->id,
                            'modifier_group_name'  => $option->modifierGroup->name,
                            'modifier_option_name' => $option->name,
                            'unit_price'           => $modPrice,
                        ]);

                        $modifierTotal += $modPrice;
                    }
                }

                $orderItem->update([
                    'subtotal' => ($basePrice + $modifierTotal) * $itemData['quantity'],
                ]);
            }

            return $order;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Order created successfully.',
            'data'    => $order->load(['items.modifiers', 'table:id,name,slug']),
        ], 201);
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $number = $prefix . '-' . random_int(100000, 999999);

        while (Order::where('order_number', $number)->exists()) {
            $number = $prefix . '-' . random_int(100000, 999999);
        }

        return $number;
    }
}