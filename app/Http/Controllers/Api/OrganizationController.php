<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organizations;
use App\Notifications\OrganizationDeletedNotification;
use App\Notifications\RestaurantOwnerInvitedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    /**
     * Get the authenticated user's organization details.
     */
    public function myOrganization(Request $request): JsonResponse
    {
        $user = $request->user();

        $organization = Organizations::with([
            'owner:id,name,email',
            'restaurants' => function ($query) {
                $query->select('id', 'organization_id', 'name', 'slug');
            }
        ])
        ->withCount('restaurants')
        ->where('owner_id', $user->id)
        ->first();

        if (! $organization) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No organization found for the authenticated user.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $organization,
        ], 200);
    }

    /**
     * Update the authenticated user's organization details.
     */
    public function updateMyOrganization(Request $request): JsonResponse
    {
        $user = $request->user();

        $organization = Organizations::where('owner_id', $user->id)->first();

        if (! $organization) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No organization found for the authenticated user.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $orgSlug = $this->generateUniqueSlug(Organizations::class, $validated['name'], $organization->id);

        $organization->update([
            'name' => $validated['name'],
            'slug' => $orgSlug,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Organization updated successfully.',
            'data'    => $organization->fresh(['owner:id,name,email']),
        ], 200);
    }

    /**
     * Soft delete an organization and send restoration email to owner.
     */
public function destroy(Request $request, int $id): JsonResponse
{
    $user = $request->user();

    $organization = Organizations::where('id', $id)
        ->where('owner_id', $user->id)
        ->first();

    if (! $organization) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Organization not found or access denied.',
        ], 404);
    }

    // Validate confirmation name
    $validated = $request->validate([
        'name' => 'required|string',
    ]);

    if (trim($validated['name']) !== $organization->name) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Organization name confirmation does not match.',
        ], 422);
    }

    DB::transaction(function () use ($organization) {
        // Soft delete triggers cascading soft-deletes via Organizations::booted()
        $organization->delete();
    });

    // Generate a secure restore token
    $token = Str::random(60);

    // Store token on organization or invitation/restore table for validation
    $organization->update([
        'restore_token' => $token,
        'restore_token_expires_at' => now()->addDays(30),
    ]);

    // Send email notification with SPA frontend URL
    $user->notify(new OrganizationDeletedNotification($organization->name, $token));

    return response()->json([
        'status'  => 'success',
        'message' => 'Organization Was Deleted. A restoration link has been sent to your email.',
    ], 200);
}



/**
 * Restore a soft-deleted organization and all child relations via token/email.
 */
public function restore(Request $request): JsonResponse
{
    // Validate request payload from frontend
    $validated = $request->validate([
        'token' => 'required|string',
        'email' => 'required|email',
    ]);

    // Find the soft-deleted organization matching the token and owner email
    $organization = Organizations::onlyTrashed()
        ->where('restore_token', $validated['token'])
        ->whereHas('owner', function ($query) use ($validated) {
            $query->where('email', $validated['email']);
        })
        ->first();

    if (! $organization) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Invalid or expired restoration token.',
        ], 404);
    }

    // Check if the token has expired
    if ($organization->restore_token_expires_at && now()->greaterThan($organization->restore_token_expires_at)) {
        return response()->json([
            'status'  => 'error',
            'message' => 'The restoration link has expired. Please contact support.',
        ], 422);
    }

    // Execute restoration inside a transaction
    DB::transaction(function () use ($organization) {
        // 1. Restore the organization first so soft-delete scope clears
        $organization->restore();

        // 2. Clear the restore token fields after model is active
        $organization->update([
            'restore_token'            => null,
            'restore_token_expires_at' => null,
        ]);
    });

    return response()->json([
        'status'  => 'success',
        'message' => 'Organization and all associated data restored successfully.',
        'data'    => $organization->fresh(['owner:id,name,email']),
    ], 200);
}
    /**
     * Permanently delete an organization and all child entities.
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $organization = Organizations::withTrashed()
            ->where('id', $id)
            ->where('owner_id', $user->id)
            ->first();

        if (! $organization) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organization not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        if (trim($validated['name']) !== $organization->name) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organization name confirmation does not match.',
            ], 422);
        }

        DB::transaction(function () use ($organization) {
            // Cascades forceDelete down through restaurants, categories, menu items, and media
            $organization->forceDelete();
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Organization and all associated data permanently purged.',
        ], 200);
    }

    /**
     * Delete/Revoke an Invitation
     */
    public function deleteInvitation($id): JsonResponse
    {
        $invitation = Invitation::find($id);

        if (! $invitation) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invitation not found.',
            ], 404);
        }

        $invitation->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Invitation deleted successfully.',
        ], 200);
    }

    /**
     * Get all organizations with owner details and restaurant count.
     */
    public function index(): JsonResponse
    {
        $organizations = Organizations::with(['owner:id,name,email'])
            ->withCount('restaurants')
            ->latest()
            ->get(['id', 'name', 'slug', 'owner_id', 'is_active', 'created_at']);

        return response()->json([
            'status' => 'success',
            'data'   => $organizations,
        ]);
    }

    /**
     * Get a single organization by ID with all of its restaurants.
     */
    public function show($id): JsonResponse
    {
        $organization = Organizations::with([
            'owner:id,name,email',
            'restaurants' => function ($query) {
                $query->select('id', 'organization_id', 'name', 'slug', 'logo', 'status', 'is_active', 'created_at');
            }
        ])
        ->withCount('restaurants')
        ->find($id);

        if (! $organization) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organization not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $organization,
        ]);
    }

    /**
     * Get all invitations.
     */
    public function getInvitations(Request $request): JsonResponse
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
            'data'   => $invitations,
        ]);
    }

    /**
     * Send Invitation Token to New Organization Owner
     */
    public function sendInvite(Request $request): JsonResponse
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
                'status'  => 'error',
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
            'email'      => $validated['email'],
            'token'      => $token,
            'invited_by' => $request->user()->id,
            'expires_at' => $expiresAt,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new RestaurantOwnerInvitedNotification($token, $invitation->expires_at));

        return response()->json([
            'status'     => 'success',
            'message'    => 'Invitation sent successfully',
            'invitation' => [
                'id'         => $invitation->id,
                'email'      => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Resend/Refresh Invitation Token
     */
    public function resendInvite(Request $request): JsonResponse
    {
        if (! $request->user() || $request->user()->roles()->first()?->slug !== 'super_admin') {
            return response()->json([
                'status'  => 'error',
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
                'status'  => 'error',
                'message' => 'No pending invitation found for this email address or it has already been accepted.',
            ], 404);
        }

        $newToken = Str::random(40);
        $newExpiresAt = now()->addHour();

        $invitation->update([
            'token'      => $newToken,
            'invited_by' => $request->user()->id,
            'expires_at' => $newExpiresAt,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new RestaurantOwnerInvitedNotification($newToken, $invitation->expires_at));

        return response()->json([
            'status'     => 'success',
            'message'    => 'Invitation resent successfully',
            'invitation' => [
                'id'         => $invitation->id,
                'email'      => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ], 200);
    }

    private function generateUniqueSlug(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug     = $baseSlug;
        $count    = 1;

        while ($modelClass::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug  = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}