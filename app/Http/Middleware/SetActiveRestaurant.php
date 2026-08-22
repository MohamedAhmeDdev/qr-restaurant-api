<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveRestaurant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Ensure user is authenticated before checking restaurant access
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 2. Validate presence of X-Restaurant-Id header
        $restaurantId = $request->header('X-Restaurant-Id');

        if (! $restaurantId || ! is_numeric($restaurantId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing or invalid active restaurant context. Please provide a valid X-Restaurant-Id header.',
            ], 400);
        }

        // 3. Find the active restaurant
        $restaurant = Restaurant::find($restaurantId);

        if (! $restaurant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Target restaurant not found.',
            ], 404);
        }

        // 4. Check Authorization: Super Admins bypass, otherwise check access rights
        if (! $user->is_super_admin) {
            $hasAccess = $user->roles()
                ->wherePivot('restaurant_id', $restaurantId)
                ->exists();

            $isOwner = $user->ownedRestaurants()
                ->where('restaurants.id', $restaurantId)
                ->exists();

            if (! $hasAccess && ! $isOwner) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to this restaurant.',
                ], 403);
            }
        }

        // 5. Inject active restaurant data into request & bind container singleton
        $request->merge([
            'active_restaurant_id' => (int) $restaurantId,
            'active_restaurant' => $restaurant,
        ]);

        app()->instance('active_restaurant', $restaurant);

        return $next($request);
    }
}