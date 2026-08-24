<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organizations;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\RestaurantOwnerInvitedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
   
 /**
     * Send Invitation Token to New Organization Owner (Expires in 1 Hour)
     */
    public function sendInvite(Request $request)
    {
        // 1. Authorize super admin access
        if (! $request->user() || ! $request->user()->is_super_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Super Admin permissions required.',
            ], 403);
        }

        // 2. Validate input parameters
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
        ]);

        // 3. Check for an existing active (pending & not expired) invitation
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

        // 4. Cleanup expired, unaccepted invitations for this email before creating a new one
        Invitation::where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->where('expires_at', '<=', now())
            ->delete();

        // 5. Generate secure token & set 1-hour expiration date
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
     * Resend/Refresh Invitation Token for a Pending or Expired Invite
     */
    public function resendInvite(Request $request)
    {
        // 1. Authorize super admin access
        if (! $request->user() || ! $request->user()->is_super_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Super Admin permissions required.',
            ], 403);
        }

        // 2. Validate request
        $validated = $request->validate([
            'email' => 'required|email|exists:invitations,email',
        ]);

        // 3. Find existing unaccepted invitation
        $invitation = Invitation::where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->first();

        if (! $invitation) {
            return response()->json([
                'status' => 'error',
                'message' => 'No pending invitation found for this email address or it has already been accepted.',
            ], 404);
        }

        // 4. Regenerate token & reset 1-hour expiration
        $newToken = Str::random(40);
        $newExpiresAt = now()->addHour();

        $invitation->update([
            'token' => $newToken,
            'invited_by' => $request->user()->id,
            'expires_at' => $newExpiresAt,
        ]);

        // 5. Dispatch Mail Notification with new token
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
     * Complete Owner Registration, Organization Creation & First Restaurant Setup
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255|unique:organizations,name',
            'restaurant_name' => 'required|string|max:255',
        ]);

        // 1. Verify invitation token
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

       // 2. Perform Atomic Creation (User + Organization + Restaurant)
        $session = DB::transaction(function () use ($validated, $invitation) {
            // Check globally if a restaurant with this name already exists
            if (Restaurant::where('name', $validated['restaurant_name'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'restaurant_name' => ['A restaurant with this name already exists.']
                ]);
            }
            // Create user account
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
               'password' => $validated['password'],
                'is_super_admin' => false,
            ]);

            // Generate unique slug for Organization
            $orgSlug = $this->generateUniqueSlug(Organizations::class, $validated['organization_name']);

            $organization = Organizations::create([
                'name' => $validated['organization_name'],
                'slug' => $orgSlug,
                'owner_id' => $user->id,
                'is_active' => true,
            ]);

            // Generate unique slug for Restaurant
            $restaurantSlug = $this->generateUniqueSlug(Restaurant::class, $validated['restaurant_name']);

            // Create primary restaurant
            $restaurant = $organization->restaurants()->create([
                'name' => $validated['restaurant_name'],
                'slug' => $restaurantSlug,
                'is_active' => true,
            ]);

            // Mark invitation as consumed
            $invitation->update(['accepted_at' => now()]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'organization' => $organization,
                'restaurant' => $restaurant,
                'token' => $token,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Organization and restaurant onboarding completed successfully.',
            'data' => [
                'user' => [
                    'id' => $session['user']->id,
                    'name' => $session['user']->name,
                    'email' => $session['user']->email,
                'is_super_admin' => false,
                ],
                'token' => $session['token'],
            ],
        ], 201);
    }

    /**
     * Helper to generate a unique slug for any Eloquent model.
     */
    private function generateUniqueSlug(string $modelClass, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $count = 1;

        while ($modelClass::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}