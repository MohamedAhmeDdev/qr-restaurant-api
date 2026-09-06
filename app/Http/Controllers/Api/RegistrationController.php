<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organizations;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Verify Invitation Token
     */
    public function verifyToken(Request $request)
    {
        $token = $request->query('token');

        $invitation = Invitation::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invitation) {
            return response()->json([
                'status' => 'error',
                'valid' => false,
                'message' => 'Invalid or expired invite token.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'valid' => true,
            'email' => $invitation->email,
        ]);
    }

    /**
     * User Registration via Invitation Token
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255|unique:organizations,name',
        ]);

        $invitation = Invitation::where('token', $validated['token'])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invitation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired invitation link.',
            ], 400);
        }

        $session = DB::transaction(function () use ($validated, $invitation) {
            // 1. Create user account
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => bcrypt($validated['password']),
            ]);

            $adminRole = Role::where('slug', 'restaurant_admin')->firstOrFail();
            $user->roles()->attach($adminRole->id, [
                'status' => 'active',
            ]);

            // 2. Create Organization
            $orgSlug = $this->generateUniqueSlug(Organizations::class, $validated['organization_name']);
            $organization = Organizations::create([
                'name' => $validated['organization_name'],
                'slug' => $orgSlug,
                'owner_id' => $user->id,
                'is_active' => true,
            ]);

            $invitation->update(['accepted_at' => now()]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user'         => $user,
                'role'         => $adminRole->slug,
                'organization' => $organization,
                'token'        => $token,
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Registration complete.',
            'data'    => [
                'user' => [
                    'id'    => $session['user']->id,
                    'name'  => $session['user']->name,
                    'email' => $session['user']->email,
                    'role'  => $session['role'],
                ],
                'organization_slug' => $session['organization']->slug,
                'token'             => $session['token'],
            ],
        ], 201);
    }

    private function generateUniqueSlug(string $modelClass, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug     = $baseSlug;
        $count    = 1;

        while ($modelClass::where('slug', $slug)->exists()) {
            $slug  = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}