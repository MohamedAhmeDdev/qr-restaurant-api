<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Eager-load roles and permissions once to prevent N+1 queries
        $user->loadMissing('roles.permissions');

        // Super admins bypass all permission checks
        if ($user->roles->contains('slug', 'super_admin')) {
            return $next($request);
        }

        if (empty($permissions)) {
            return $next($request);
        }

        // Check against pre-loaded collections in memory
        $hasPermission = $user->roles->pluck('permissions')->flatten()->contains(function ($permission) use ($permissions) {
            return in_array($permission->slug, $permissions, true);
        });

        if (! $hasPermission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}