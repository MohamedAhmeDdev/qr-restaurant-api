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

        if (! $user || ! $user->roles()->where('slug', 'super_admin')->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. Super Admin access required.'
            ], 403);
        }

        return $next($request);
    }
}