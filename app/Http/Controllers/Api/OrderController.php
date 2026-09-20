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

        $query = Order::where('restaurant_id', $restaurant->id);
        $statsQuery = Order::where('restaurant_id', $restaurant->id);

        // 1. Date filters (supports both start_date/end_date and legacy date_from/date_to)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $dateCondition = [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59'];
            $query->whereBetween('created_at', $dateCondition);
            $statsQuery->whereBetween('created_at', $dateCondition);
        } elseif ($request->filled('date_from') && $request->filled('date_to')) {
            $dateCondition = [$request->date_from . ' 00:00:00', $request->date_to . ' 23:59:59'];
            $query->whereBetween('created_at', $dateCondition);
            $statsQuery->whereBetween('created_at', $dateCondition);
        } elseif ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
            $statsQuery->whereDate('created_at', $request->date);
        } else {
            // Default to last 7 days to match frontend default
            $defaultStart = now()->subDays(6)->startOfDay();
            $defaultEnd = now()->endOfDay();
            $query->whereBetween('created_at', [$defaultStart, $defaultEnd]);
            $statsQuery->whereBetween('created_at', [$defaultStart, $defaultEnd]);
        }

        // 2. Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // 3. Search filter (Order Number or Table Name/Number)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('table', function ($tq) use ($search) {
                      $tq->where('name', 'like', "%{$search}%")
                         ->orWhere('table_number', 'like', "%{$search}%");
                  });
            });
        }

        // 4. Calculate stats for the current date range (ignoring search/status to show overall context)
        $stats = [
            'total'     => (clone $statsQuery)->count(),
            'pending'   => (clone $statsQuery)->where('status', 'pending')->count(),
            'preparing' => (clone $statsQuery)->where('status', 'preparing')->count(),
            'ready'     => (clone $statsQuery)->where('status', 'ready')->count(),
            'served'    => (clone $statsQuery)->where('status', 'served')->count(),
            'cancelled' => (clone $statsQuery)->where('status', 'cancelled')->count(),
        ];

        // 5. Execute paginated query
        $query->with(['table:id,name,table_number', 'items.modifiers']);
        $perPage = $request->integer('per_page', 15);
        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'stats'  => $stats,
            'data'   => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $order = Order::where('restaurant_id', $restaurant->id)
            ->with(['table:id,name', 'items.modifiers'])
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

        $order->update($updates);

        return response()->json([
            'status'  => 'success',
            'message' => 'Order status updated successfully.',
            'data'    => $order->fresh(['table:id,name']),
        ]);
    }
}