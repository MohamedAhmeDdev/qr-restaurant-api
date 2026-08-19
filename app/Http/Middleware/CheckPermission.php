<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$permissions
    ): Response {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Authentication check
        |--------------------------------------------------------------------------
        */

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Permission check
        |--------------------------------------------------------------------------
        */

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Authorization failed
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' => 'You do not have permission to perform this action.',
        ], 403);
    }
}