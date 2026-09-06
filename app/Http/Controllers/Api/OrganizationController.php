<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organizations;
use App\Notifications\RestaurantOwnerInvitedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    /**
     * Get all organizations with owner details and restaurant count.
     */
    public function index()
    {
        $organizations = Organizations::with(['owner:id,name,email'])
            ->withCount('restaurants')
            ->latest()
            ->get(['id', 'name', 'slug', 'owner_id', 'is_active', 'created_at']);

        return response()->json([
            'status' => 'success',
            'data' => $organizations,
        ]);
    }

    /**
     * Get a single organization by ID with all of its restaurants.
     */
    public function show($id)
    {
        $organization = Organizations::with([
            'owner:id,name,email',
            'restaurants' => function ($query) {
                $query->select('id', 'organization_id', 'name', 'slug', 'logo', 'status', 'is_active', 'created_at');
            }
        ])
        ->withCount('restaurants')
        ->find($id);

        if (!$organization) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organization not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $organization,
        ]);
    }

    /**
     * Get all invitations.
     */
    public function getInvitations(Request $request)
    {
        $query = Invitation::with([
            'organization:id,name',
            'restaurant:id,name',
            'invitedBy:id,name,email'
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $invitations = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $invitations,
        ]);
    }

    /**
     * Send Invitation Token to New Organization Owner
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
     * Delete/Revoke an Invitation
     */
    public function deleteInvitation($id)
    {
        $invitation = Invitation::find($id);

        if (! $invitation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitation not found.',
            ], 404);
        }

        $invitation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Invitation deleted successfully.',
        ], 200);
    }
}