<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Models\Table;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuestTableAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $restaurantSlug = $request->route('restaurantSlug');
        $tableSlug = $request->route('tableSlug');

        $restaurant = Restaurant::where('slug', $restaurantSlug)->first();

        if (! $restaurant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Restaurant not found.',
            ], 404);
        }

        if ($restaurant->status !== 'active' || ! $restaurant->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Restaurant is currently unavailable.',
            ], 403);
        }

        $table = Table::where('restaurant_id', $restaurant->id)
            ->where('slug', $tableSlug)
            ->first();

        if (! $table) {
            return response()->json([
                'status' => 'error',
                'message' => 'Table not found.',
            ], 404);
        }

        if (! $table->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Table is currently unavailable.',
            ], 403);
        }

        $token = $request->query('token');
        if ($token !== $table->token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid table token.',
            ], 403);
        }

        $request->attributes->set('restaurant', $restaurant);
        $request->attributes->set('table', $table);

        return $next($request);
    }
}