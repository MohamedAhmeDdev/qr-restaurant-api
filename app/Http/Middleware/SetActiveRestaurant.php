<?php

namespace App\Http\Middleware;

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
        // 1. Get the restaurant ID from the request header
        $restaurantId = $request->header('X-Restaurant-Id');

        if (!$restaurantId) {
            return response()->json([
                'message' => 'No active restaurant selected. Please provide X-Restaurant-Id header.'
            ], 400);
        }

        $user = $request->user();

        // 2. Super Admins can access any restaurant
        if (!$user->is_super_admin) {
            
            // 3. Check if the user actually has a role at this specific restaurant
            $hasAccess = $user->roles()
                ->wherePivot('restaurant_id', $restaurantId)
                ->exists();

            // Also check if they are the direct owner (in case owner doesn't have a row in user_roles yet)
            $isOwner = $user->ownedRestaurants()->where('restaurants.id', $restaurantId)->exists();

            if (!$hasAccess && !$isOwner) {
                return response()->json([
                    'message' => 'Unauthorized access to this restaurant.'
                ], 403); // 403 Forbidden
            }
        }

        // 4. Inject the verified restaurant_id into the request so controllers can use it easily
        $request->merge(['active_restaurant_id' => (int) $restaurantId]);

        return $next($request);
    }
}