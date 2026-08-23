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

        // Check if user exists and is_super_admin boolean is true
        if (! $user || ! $user->is_super_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. Super Admin access required.'
            ], 403);
        }

        return $next($request);
    }
}