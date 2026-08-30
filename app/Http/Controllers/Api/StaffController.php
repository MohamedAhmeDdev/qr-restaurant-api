<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    /**
     * Helper to resolve a single Role model instance by ID, slug, or name.
     */
    private function resolveRole(mixed $roleInput): ?Role
    {
        if (empty($roleInput)) {
            return null;
        }

        return Role::where(function ($q) use ($roleInput) {
            if (is_numeric($roleInput)) {
                $q->where('roles.id', (int) $roleInput);
            }
            $q->orWhere('roles.slug', strtolower((string) $roleInput))
              ->orWhere('roles.name', strtolower((string) $roleInput));
        })->first();
    }

    /**
     * Helper to standardize staff user response payload with single role.
     */
    private function formatStaffResponse(User $user): array
    {
        $assignedPivot = $user->assignedRestaurants->first()?->pivot;
        $primaryRole = $user->roles->first();

        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $primaryRole ? [
                'id'   => $primaryRole->id,
                'name' => $primaryRole->name,
                'slug' => $primaryRole->slug,
            ] : null,
            'status'     => $assignedPivot?->status,
            'shift_type' => $assignedPivot?->shift_type,
        ];
    }

    /**
     * List staff assigned to the CURRENT restaurant only.
     */
public function index(Request $request)
{
    $restaurant = $request->get('restaurant');

    // Base query scoped to non-deleted staff at the current restaurant
    $baseStaffQuery = Staff::where('restaurant_id', $restaurant->id)
        ->whereNull('deleted_at');

    // Aggregate statistics
    $stats = [
        'total'  => (clone $baseStaffQuery)->count(),
        'active' => (clone $baseStaffQuery)->where('status', 'active')->count(),
    ];

    $query = User::select(['users.id', 'users.name', 'users.email'])
        ->with([
            'roles:roles.id,roles.name,roles.slug',
            'assignedRestaurants' => fn ($q) => $q->where('restaurants.id', $restaurant->id)
        ])
        ->whereHas('assignedRestaurants', function ($q) use ($restaurant, $request) {
            $q->where('restaurants.id', $restaurant->id);

            if ($request->boolean('only_trashed')) {
                $q->whereNotNull('staff.deleted_at');
            } elseif (! $request->boolean('with_trashed')) {
                $q->whereNull('staff.deleted_at');
            }
        });

    // Search Name/Email
    $query->when($request->filled('search'), function ($q) use ($request) {
        $search = $request->search;
        $q->where(function ($sub) use ($search) {
            $sub->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    });

    // Role Filter
    $query->when($request->filled('role_id'), function ($q) use ($request) {
        $roleInput = $request->role_id;
        $q->whereHas('roles', function ($r) use ($roleInput) {
            $r->where(function ($sub) use ($roleInput) {
                if (is_numeric($roleInput)) {
                    $sub->where('roles.id', (int) $roleInput);
                }
                $sub->orWhere('roles.slug', strtolower((string) $roleInput))
                    ->orWhere('roles.name', (string) $roleInput);
            });
        });
    });

    // Status Filter
    $query->when($request->filled('status'), function ($q) use ($restaurant, $request) {
        $q->whereHas('assignedRestaurants', fn ($r) => $r
            ->where('restaurants.id', $restaurant->id)
            ->where('staff.status', $request->status));
    });

    // Shift Type Filter
    $query->when($request->filled('shift_type'), function ($q) use ($restaurant, $request) {
        $q->whereHas('assignedRestaurants', fn ($r) => $r
            ->where('restaurants.id', $restaurant->id)
            ->where('staff.shift_type', $request->shift_type));
    });

    $perPage = $request->integer('per_page', 15);
    $paginator = $query->latest('users.created_at')
        ->paginate($perPage)
        ->through(fn ($user) => $this->formatStaffResponse($user));

    return response()->json([
        'status'     => 'success',
        'stats'      => $stats,
        'data'       => $paginator->items(),
        'pagination' => [
            'total'          => $paginator->total(),
            'per_page'       => $paginator->perPage(),
            'current_page'   => $paginator->currentPage(),
            'last_page'      => $paginator->lastPage(),
            'from'           => $paginator->firstItem(),
            'to'             => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
        ],
    ]);
}

    /**
     * Create a user (or find existing) and assign them to the CURRENT restaurant.
     */
    public function store(Request $request)
    {
        $restaurant = $request->get('restaurant');

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'password'   => 'nullable|string|min:8',
            'role_id'    => 'nullable',
            'role'       => 'nullable',
            'status'     => 'required|string|in:active,inactive,on_leave',
            'shift_type' => 'required|string|in:day,night,full_time,flexible',
        ]);

        return DB::transaction(function () use ($validated, $restaurant) {
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'     => $validated['name'],
                    'password' => Hash::make($validated['password'] ?? Str::random(12)),
                ]
            );

            if ($user->name !== $validated['name']) {
                $user->update(['name' => $validated['name']]);
            }

            // 1. Resolve & Sync Role
            $roleInput = $validated['role_id'] ?? $validated['role'] ?? null;
            $role = $this->resolveRole($roleInput);

            if ($role) {
                $user->roles()->sync([$role->id]);
            }

            // 2. Attach or Update Pivot
            $pivot = Staff::withTrashed()
                ->where('user_id', $user->id)
                ->where('restaurant_id', $restaurant->id)
                ->first();

            $status    = $validated['status'] ?? 'active';
            $shiftType = $validated['shift_type'] ?? 'day';

            if ($pivot) {
                if ($pivot->trashed()) {
                    $pivot->restore();
                }
                $pivot->update([
                    'status'     => $status,
                    'shift_type' => $shiftType,
                ]);
            } else {
                Staff::create([
                    'user_id'       => $user->id,
                    'restaurant_id' => $restaurant->id,
                    'status'        => $status,
                    'shift_type'    => $shiftType,
                ]);
            }

            $user->load([
                'roles:roles.id,roles.name,roles.slug',
                'assignedRestaurants' => fn ($q) => $q->where('restaurants.id', $restaurant->id)
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Staff member created and assigned successfully.',
                'data'    => $this->formatStaffResponse($user),
            ], 201);
        });
    }

    /**
     * Show single staff details (scoped to current workspace).
     */
    public function show(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        $staff = User::select(['users.id', 'users.name', 'users.email'])
            ->with([
                'roles:roles.id,roles.name,roles.slug',
                'assignedRestaurants' => fn ($q) => $q->where('restaurants.id', $restaurant->id)
            ])
            ->whereHas('assignedRestaurants', function ($q) use ($restaurant) {
                $q->where('restaurants.id', $restaurant->id)
                  ->whereNull('staff.deleted_at');
            })
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatStaffResponse($staff),
        ]);
    }

    /**
     * Update staff details in current restaurant workspace.
     */
    public function update(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|max:255|unique:users,email,' . $id,
            'role_id'    => 'nullable',
            'role'       => 'nullable',
            'status'     => 'sometimes|string|in:active,inactive,pending,on_leave',
            'shift_type' => 'sometimes|string|in:day,night,full_time,flexible',
        ]);

        $staff = User::whereHas('assignedRestaurants', function ($q) use ($restaurant) {
            $q->where('restaurants.id', $restaurant->id)
              ->whereNull('staff.deleted_at');
        })->findOrFail($id);

        return DB::transaction(function () use ($staff, $validated, $restaurant) {
            $userUpdates = array_filter([
                'name'  => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
            ]);
            if (!empty($userUpdates)) {
                $staff->update($userUpdates);
            }

            // 1. Resolve & Sync Role (if passed)
            $roleInput = $validated['role_id'] ?? $validated['role'] ?? null;
            if ($roleInput) {
                $role = $this->resolveRole($roleInput);
                if ($role) {
                    $staff->roles()->sync([$role->id]);
                }
            }

            // 2. Update Pivot fields
            $pivotUpdates = array_filter([
                'status'     => $validated['status'] ?? null,
                'shift_type' => $validated['shift_type'] ?? null,
            ]);

            if (!empty($pivotUpdates)) {
                Staff::where('user_id', $staff->id)
                    ->where('restaurant_id', $restaurant->id)
                    ->update($pivotUpdates);
            }

            $staff->load([
                'roles:roles.id,roles.name,roles.slug',
                'assignedRestaurants' => fn ($q) => $q->where('restaurants.id', $restaurant->id)
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Staff updated successfully.',
                'data'    => $this->formatStaffResponse($staff),
            ]);
        });
    }

    /**
     * Soft delete staff from CURRENT restaurant workspace.
     */
    public function destroy(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        Staff::where('user_id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff removed from this restaurant.',
        ]);
    }

    /**
     * Restore soft-deleted staff in CURRENT restaurant workspace.
     */
    public function restore(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        Staff::onlyTrashed()
            ->where('user_id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->restore();

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff restored to this restaurant.',
        ]);
    }

    /**
     * Permanently remove staff from CURRENT restaurant workspace.
     */
    public function forceDelete(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        Staff::withTrashed()
            ->where('user_id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->forceDelete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff permanently removed from this restaurant.',
        ]);
    }
}