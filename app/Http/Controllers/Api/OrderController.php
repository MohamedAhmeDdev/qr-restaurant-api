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
            ->with(['table:id,name,slug', 'items.modifiers']);

        // Default to current day if no date filters are provided
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59',
            ]);
        } elseif ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        } else {
            $query->whereDate('created_at', now()->toDateString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', $request->table_id);
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
            ->with(['table', 'items.modifiers'])
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
            'status' => 'required|string|in:pending,preparing,ready,completed,cancelled',
        ]);

        $order = Order::where('restaurant_id', $restaurant->id)->find($id);

        if (! $order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order not found.',
            ], 404);
        }

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'completed') {
            $updates['completed_at'] = now();
        }

        $order->update($updates);

        return response()->json([
            'status'  => 'success',
            'message' => 'Order status updated successfully.',
            'data'    => $order->fresh(),
        ]);
    }
}