<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\StoreGuestOrderRequest;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestOrderController extends Controller
{
    public function store(StoreGuestOrderRequest $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');
        $table = $request->attributes->get('table');

        // Retrieve pre-validated request data and verified totals
        $validated = $request->validated();
        $totals = $request->getVerifiedTotals();

        $order = DB::transaction(function () use ($restaurant, $table, $validated, $totals) {
            $order = Order::create([
                'restaurant_id'  => $restaurant->id,
                'table_id'       => $table->id,
                'order_number'   => $this->generateOrderNumber(),
                'status'         => 'pending',
                'payment_status' => 'pending',
                'type'           => $validated['type'],
                'notes'          => $validated['notes'] ?? null,
                'subtotal'       => $totals['subtotal'],
                'total_amount'   => $totals['total_amount'],
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
        $random = strtoupper(Str::random(6));
        $number = $prefix . '-' . $random;

        while (Order::where('order_number', $number)->exists()) {
            $random = strtoupper(Str::random(6));
            $number = $prefix . '-' . $random;
        }

        return $number;
    }
}