<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantAccess
{
    /**
     * Handle workspace access validation using X-Restaurant-Slug header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 1. Extract active workspace header
        $slug = $request->header('X-Restaurant-Slug');

        if (! $slug) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing X-Restaurant-Slug header in request.',
            ], 400);
        }

        // 2. Fetch active restaurant workspace
        $restaurant = Restaurant::withTrashed()->where('slug', $slug)->first();

        if (! $restaurant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Restaurant workspace not found.',
            ], 404);
        }

        // 3. Check Organization Ownership
        $ownedOrg = $user->ownedOrganizations()->first();
        if ($ownedOrg && $restaurant->organization_id === $ownedOrg->id) {
            $request->merge(['restaurant' => $restaurant]);
            return $next($request);
        }

        // 4. Check Assigned Staff via Pivot
        $isAssigned = $user->assignedRestaurants()
            ->where('restaurants.id', $restaurant->id)
            ->whereNull('staff.deleted_at')
            ->exists();

        if ($isAssigned) {
            $request->merge(['restaurant' => $restaurant]);
            return $next($request);
        }

        // 5. Access Denied
        return response()->json([
            'status' => 'error',
            'message' => 'Forbidden. You do not have access to this restaurant workspace.',
        ], 403);
    }
}