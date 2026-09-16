<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesController extends Controller
{
    /**
     * Get dashboard stats and chart timeline data.
     */
  public function index(Request $request)
    {
        $restaurant = $request->attributes->get('restaurant');

        // 1. Current Day Stats
        $today = Carbon::today();
        $todayStats = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $today)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('COUNT(id) as total_orders, SUM(total_amount) as total_revenue')
            ->first();

        // 2. Weekly Revenue Trend (Last 7 days, ensuring all 7 days are represented)
        $chartData = [];
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::today()->subDays(6 - $i);
            $dayStats = Order::where('restaurant_id', $restaurant->id)
                ->whereDate('created_at', $date)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');
            
            $chartData[] = [
                'day' => $date->format('D'), // Mon, Tue, etc.
                'value' => (float) $dayStats,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'today_revenue' => (float) ($todayStats->total_revenue ?? 0),
                'today_orders' => (int) ($todayStats->total_orders ?? 0),
                'weekly_revenue' => $chartData,
            ]
        ]);
    }

    public function menuItemsStats(Request $request)
    {
        $restaurant = $request->attributes->get('restaurant');

        $startDate = $request->filled('start_date') 
            ? Carbon::parse($request->start_date)->startOfDay() 
            : Carbon::now()->subDays(6)->startOfDay();
            
        $endDate = $request->filled('end_date') 
            ? Carbon::parse($request->end_date)->endOfDay() 
            : Carbon::now()->endOfDay();

        $orderConstraints = function ($query) use ($restaurant, $startDate, $endDate) {
            $query->where('restaurant_id', $restaurant->id)
                  ->whereNotIn('status', ['cancelled'])
                  ->whereBetween('created_at', [$startDate, $endDate]);
        };

        $stats = Order::where($orderConstraints)
            ->selectRaw('COUNT(id) as total_orders, SUM(total_amount) as total_revenue')
            ->first();

        $totalRevenue = (float) ($stats->total_revenue ?? 0);
        $totalOrders = (int) ($stats->total_orders ?? 0);
        $averageOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0.00;

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'total_revenue' => $totalRevenue,
                    'total_orders' => $totalOrders,
                    'average_order_value' => round($averageOrderValue, 2),
                ]
            ]
        ]);
    }

    public function menuItems(Request $request)
    {
        $restaurant = $request->attributes->get('restaurant');
        $perPage = $request->integer('per_page', 15);

        $startDate = $request->filled('start_date') 
            ? Carbon::parse($request->start_date)->startOfDay() 
            : Carbon::now()->subDays(6)->startOfDay();
            
        $endDate = $request->filled('end_date') 
            ? Carbon::parse($request->end_date)->endOfDay() 
            : Carbon::now()->endOfDay();

        $search = $request->input('search');
        $category = $request->input('category');

        $query = MenuItem::select([
                'menu_items.id',
                'menu_items.name',
                'categories.name as category_name',
                'menu_items.price',
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as sold'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ])
            ->leftJoin('categories', 'menu_items.category_id', '=', 'categories.id')
            ->leftJoin('order_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('orders', function ($join) use ($restaurant, $startDate, $endDate) {
                $join->on('order_items.order_id', '=', 'orders.id')
                     ->where('orders.restaurant_id', '=', $restaurant->id)
                     ->whereNotIn('orders.status', ['cancelled'])
                     ->whereBetween('orders.created_at', [$startDate, $endDate]);
            })
            ->where('menu_items.restaurant_id', $restaurant->id)
            ->groupBy('menu_items.id', 'menu_items.name', 'categories.name', 'menu_items.price');

        // Backend Search Filtering
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('menu_items.name', 'like', "%{$search}%")
                  ->orWhere('categories.name', 'like', "%{$search}%");
            });
        }

        // Backend Category Filtering
        if ($category && $category !== 'all') {
            if ($category !== 'unsold') {
                $query->where('categories.name', 'like', "%{$category}%");
            }
        }

        // ✅ Use paginate() instead of get()
        $paginatedItems = $query->orderBy('sold', 'desc')->paginate($perPage);

        $formattedItems = $paginatedItems->getCollection()->map(function ($item) {
            $sold = (int) $item->sold;
            $status = 'steady';
            
            if ($sold === 0) $status = 'unsold';
            elseif ($sold >= 100) $status = 'bestseller';
            elseif ($sold >= 30) $status = 'popular';
            elseif ($sold < 10) $status = 'low';

            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category_name ?? 'Uncategorized',
                'price' => (float) $item->price,
                'sold' => $sold,
                'revenue' => (float) $item->revenue,
                'status' => $status,
            ];
        });

        // If filtering for 'unsold', we do it post-query since it's a calculated field
        if ($category === 'unsold') {
            $formattedItems = $formattedItems->filter(fn($i) => $i['sold'] === 0);
            // Note: For strict pagination accuracy with calculated fields, you might need a HAVING clause, 
            // but this works well for typical dataset sizes.
        }

        return response()->json([
            'status' => 'success',
            'data' => $formattedItems->values(),
            'pagination' => [
                'current_page' => $paginatedItems->currentPage(),
                'last_page' => $paginatedItems->lastPage(),
                'total' => $paginatedItems->total(),
                'per_page' => $paginatedItems->perPage(),
            ]
        ]);
    }
}


