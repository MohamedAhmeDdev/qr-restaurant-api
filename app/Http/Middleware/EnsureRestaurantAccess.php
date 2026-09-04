<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('X-Restaurant-Slug');

        if (! $slug) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Missing X-Restaurant-Slug header.',
            ], 400);
        }

        $restaurant = Restaurant::withTrashed()->where('slug', $slug)->first();

        if (! $restaurant || $restaurant->trashed()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Restaurant workspace not found.',
            ], 404);
        }

        $user = $request->user();

        // 1. System Deactivation Check (Super Admin Global Switch)
        if (! $restaurant->is_active) {
            return $this->forceLogoutResponse($user, 'This restaurant workspace has been deactivated by system administration.');
        }

        // 2. Organization Owner Check (Allowed even when 'suspended' so they can reactivate)
        $ownedOrg = $user->ownedOrganizations()->first();
        if ($ownedOrg && $restaurant->organization_id === $ownedOrg->id) {
            $request->attributes->set('restaurant', $restaurant);
            return $next($request);
        }

        // 3. Owner Suspension Check: Block & revoke staff sessions if suspended by owner
        if ($restaurant->status === 'suspended') {
            return $this->forceLogoutResponse($user, 'Restaurant operations have been suspended by the owner.');
        }

        // 4. Staff Assignment & User Role Status Verification
        $staffAssignment = $user->assignedRestaurants()
            ->where('restaurants.id', $restaurant->id)
            ->whereNull('staff.deleted_at')
            ->first();

        if ($staffAssignment) {
            // Retrieve role status explicitly scoped to THIS restaurant workspace
            $userRole = $user->roles()
                ->wherePivot('restaurant_id', $restaurant->id)
                ->whereNull('user_roles.deleted_at')
                ->first();

            $roleStatus = $userRole?->pivot->status ?? 'deactivated';

            if ($roleStatus !== 'active') {
                return $this->forceLogoutResponse($user, "Your account status for this workspace is currently '{$roleStatus}'. Session terminated.");
            }

            $request->attributes->set('restaurant', $restaurant);
            return $next($request);
        }

        // Default denial for unassigned users
        return $this->forceLogoutResponse($user, 'Forbidden. You do not have access to this workspace.');
    }

    /**
     * Revokes the current access token and returns a 401 response to trigger frontend logout.
     */
    private function forceLogoutResponse($user, string $message): Response
    {
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete(); // Revokes current Sanctum bearer token
        }

        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'force_logout' => true,
        ], 401);
    }
}