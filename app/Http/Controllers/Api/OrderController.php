<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $query = Order::where('restaurant_id', $restaurant->id)
            ->with(['table:id,name,slug', 'staff:id,name', 'items.modifiers']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $perPage = $request->integer('per_page', 20);

        return response()->json([
            'status' => 'success',
            'data'   => $query->latest()->paginate($perPage),
        ]);
    }


    public function show(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $order = Order::where('restaurant_id', $restaurant->id)
            ->with(['table', 'staff:id,name', 'items.modifiers'])
            ->find($id);

        if (! $order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $order,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $validated = $request->validate([
            'status' => 'required|string|in:pending,preparing,ready,served,cancelled',
        ]);

        $order = Order::where('restaurant_id', $restaurant->id)->find($id);

        if (! $order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order not found.',
            ], 404);
        }

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'served') {
            $updates['completed_at'] = now();
        }

        if ($validated['status'] === 'cancelled' && $order->payment_status === 'paid') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cannot cancel a paid order directly. Process a refund first.',
            ], 422);
        }

        $order->update($updates);

        return response()->json([
            'status'  => 'success',
            'message' => 'Order status updated successfully.',
            'data'    => $order,
        ]);
    }

   
}