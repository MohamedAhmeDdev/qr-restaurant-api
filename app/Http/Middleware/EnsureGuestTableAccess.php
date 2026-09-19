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
        // Read directly from HTTP headers instead of route parameters
        $restaurantSlug = $request->header('X-Restaurant-Slug');
        $tableSlug      = $request->header('X-Table-Slug');

        if (! $restaurantSlug || ! $tableSlug) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Missing restaurant or table contextual headers.',
            ], 400);
        }

        $restaurant = Restaurant::where('slug', $restaurantSlug)->first();

        if (! $restaurant) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Restaurant not found.',
            ], 404);
        }

        if (! $restaurant->is_active || $restaurant->status !== 'active') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Restaurant is currently unavailable for guest ordering.',
            ], 403);
        }

        $table = Table::where('restaurant_id', $restaurant->id)
            ->where('slug', $tableSlug)
            ->first();

        if (! $table) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Table not found.',
            ], 404);
        }

        if (! $table->is_active) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Table is currently unavailable.',
            ], 403);
        }

        $token = $request->header('X-Table-Token');
        if (! $token || ! hash_equals((string) $table->token, (string) $token)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid or missing table scan token.',
            ], 403);
        }

        $request->attributes->set('restaurant', $restaurant);
        $request->attributes->set('table', $table);

        return $next($request);
    }
}