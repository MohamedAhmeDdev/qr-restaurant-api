<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organizations;
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
     * Send Invitation Token to New Organization Owner (Expires in 1 Hour)
     */
    public function sendInvite(Request $request)
    {

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
        ]);

        $existingInvitation = Invitation::where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'status' => 'error',
                'message' => 'An active invitation has already been sent to this email address.',
            ], 422);
        }

        Invitation::where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $token = Str::random(40);
        $expiresAt = now()->addHour();

        $invitation = Invitation::create([
            'email' => $validated['email'],
            'token' => $token,
            'invited_by' => $request->user()->id,
            'expires_at' => $expiresAt,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new RestaurantOwnerInvitedNotification($token, $invitation->expires_at));

        return response()->json([
            'status' => 'success',
            'message' => 'Invitation sent successfully',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Resend/Refresh Invitation Token
     */
    public function resendInvite(Request $request)
    {
        if (! $request->user() || $request->user()->roles()->first()?->slug !== 'super_admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Super Admin permissions required.',
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|exists:invitations,email',
        ]);

        $invitation = Invitation::where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->first();

        if (! $invitation) {
            return response()->json([
                'status' => 'error',
                'message' => 'No pending invitation found for this email address or it has already been accepted.',
            ], 404);
        }

        $newToken = Str::random(40);
        $newExpiresAt = now()->addHour();

        $invitation->update([
            'token' => $newToken,
            'invited_by' => $request->user()->id,
            'expires_at' => $newExpiresAt,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new RestaurantOwnerInvitedNotification($newToken, $invitation->expires_at));

        return response()->json([
            'status' => 'success',
            'message' => 'Invitation resent successfully',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ], 200);
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
     * Registration
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
            // 1. Create account
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
            ]);

            $adminRole = Role::where('slug', 'restaurant_admin')->firstOrFail();
            $user->roles()->attach($adminRole->id, [
                'status' => 'active',
            ]);

            // 2. Create Organization as inactive
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