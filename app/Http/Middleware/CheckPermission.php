<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Check if the authenticated user has any of the specified permissions.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super admins automatically bypass permission checks
        if ($user->roles()->where('slug', 'super_admin')->exists()) {
            return $next($request);
        }

        // Check if user has a role with any of the required permission slugs
        if (! empty($permissions)) {
            $hasPermission = $user->roles()
                ->whereHas('permissions', function ($query) use ($permissions) {
                    $query->whereIn('slug', $permissions);
                })
                ->exists();

            if (! $hasPermission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden. You do not have permission to perform this action.',
                ], 403);
            }
        }

        return $next($request);
    }
}