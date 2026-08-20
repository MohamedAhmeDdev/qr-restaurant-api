<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RestaurantOwnerInvitedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Send Invitation Token to New Restaurant Owner
     */
    public function sendInvite(Request $request)
    {
        // 1. Authorize super admin access
        if (!$request->user() || !$request->user()->is_super_admin) {
            return response()->json([
                'message' => 'Unauthorized. Super Admin permissions required.'
            ], 403);
        }

        // 2. Validate input parameters
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'role_id' => 'nullable|exists:roles,id',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ]);

        // 3. Resolve role ID (default to Restaurant Admin)
        $roleId = $validated['role_id'] ?? null;
        if (!$roleId) {
            $adminRole = Role::where('name', 'Restaurant Admin')
                ->orWhere('slug', 'restaurant-admin')
                ->first();
            $roleId = $adminRole?->id;
        }

        // 4. Invalidate old pending invitations for this email
        Invitation::where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->delete();

        // 5. Generate secure token & expiration date
        $token = Str::random(40);
        $expiresInDays = $validated['expires_in_days'] ?? 7;
        $expiresAt = now()->addDays($expiresInDays);

        $invitation = Invitation::create([
            'email' => $validated['email'],
            'token' => $token,
            'role_id' => $roleId,
            'invited_by' => $request->user()->id,
            'expires_at' => $expiresAt,
        ]);

        // 6. Build onboarding link (frontend registration route)
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $inviteUrl = "{$frontendUrl}/register?token={$token}";

        // 7. Dispatch Mail Notification
        Notification::route('mail', $invitation->email)
            ->notify(new RestaurantOwnerInvitedNotification($inviteUrl, $invitation->expires_at));

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'invite_url' => $inviteUrl,
            ],
        ], 201);
    }

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

        if (!$invitation) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired invite token.'], 404);
        }

        return response()->json([
            'valid' => true,
            'email' => $invitation->email,
        ]);
    }

    /**
     * Complete Owner Registration & Restaurant Creation
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255|unique:users,name',
            'password' => 'required|string|min:8|confirmed',
            'restaurant_name' => 'required|string|max:255|unique:restaurants,name',
            'restaurant_slug' => 'required|string|max:255',
        ]);

        // 1. Verify invitation token
        $invitation = Invitation::where('token', $validated['token'])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invalid or expired invitation link.'], 400);
        }

        // 2. Fetch or Default to "Restaurant Admin" Role
        $adminRole = Role::where('name', 'Restaurant Admin')
            ->orWhere('slug', 'restaurant-admin')
            ->first();

        $roleId = $invitation->role_id ?? $adminRole?->id;

        if (!$roleId) {
            return response()->json(['message' => 'Default Restaurant Admin role is not configured in the system.'], 500);
        }

        // 3. Perform Atomic Creation (User + Restaurant + Assignments)
        $session = DB::transaction(function () use ($validated, $invitation, $roleId) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
                'is_super_admin' => false,
            ]);

            $restaurant = Restaurant::create([
                'name' => $validated['restaurant_name'],
                'slug' => Str::slug($validated['restaurant_slug']),
                'owner_id' => $user->id,
                'is_active' => true,
            ]);

            // Attach user to restaurant pivot
            $user->restaurants()->attach($restaurant->id, [
                'role_id' => $roleId,
            ]);

            $invitation->update(['accepted_at' => now()]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'restaurant' => $restaurant,
                'token' => $token,
            ];
        });

        // 4. Resolve normalized user data for Restaurant Admin registration
        $user = $session['user'];

        return response()->json([
            'message' => 'Account and restaurant onboarding completed successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => false,
                'role' => 'restaurant_admin',
            ],
            'token' => $session['token'],
        ], 201);
    }
}