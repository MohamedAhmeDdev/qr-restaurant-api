<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organizations;
use App\Models\Invitation;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * 1. Get all organizations with their owner details and total restaurant count.
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
     * 2. Get a single organization by ID with all of its restaurants.
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
     * 3. Get all pending or sent system invitations.
     */
    public function getInvitations(Request $request)
    {
        // Fetches all invitations with optional status filter (e.g. ?status=pending)
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
}