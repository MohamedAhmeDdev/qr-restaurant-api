<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user exists and has the 'super_admin' role slug
        if (! $user || $user->roles()->first()?->slug !== 'super_admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. Super Admin access required.'
            ], 403);
        }

        return $next($request);
    }
}