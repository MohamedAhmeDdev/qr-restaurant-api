<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\StaffWelcomeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    private function resolveRole(mixed $roleInput): ?Role
    {
        if (empty($roleInput)) {
            return null;
        }

        return Role::where(function ($q) use ($roleInput) {
            if (is_numeric($roleInput)) {
                $q->where('id', (int) $roleInput);
            } else {
                $term = strtolower((string) $roleInput);
                $q->whereRaw('LOWER(slug) = ?', [$term])
                  ->orWhereRaw('LOWER(name) = ?', [$term]);
            }
        })->first();
    }

    private function formatStaffResponse(User $user, ?int $restaurantId = null): array
    {
        $primaryRole = $user->roles->first();
        
        // Prioritize trashed pivot if it exists, ensuring the UI correctly reflects trashed state
        $trashedPivot = $user->assignedRestaurants->first(function ($restaurant) {
            return !empty($restaurant->pivot?->deleted_at);
        })?->pivot;

        $assignedPivot = $trashedPivot ?? ($restaurantId 
            ? $user->assignedRestaurants->firstWhere('id', $restaurantId)?->pivot 
            : $user->assignedRestaurants->first()?->pivot);

        // Map all assigned restaurants for global organization views
        $restaurants = $user->assignedRestaurants->map(function ($restaurant) {
            return [
                'id'         => $restaurant->id,
                'name'       => $restaurant->name,
                'slug'       => $restaurant->slug,
                'shift_type' => $restaurant->pivot?->shift_type,
                'started_at' => $restaurant->pivot?->created_at,
                'deleted_at' => $restaurant->pivot?->deleted_at, // Added deleted_at
            ];
        });

        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $primaryRole ? [
                'id'     => $primaryRole->id,
                'name'   => $primaryRole->name,
                'slug'   => $primaryRole->slug,
                'status' => $primaryRole->pivot?->status ?? 'active',
            ] : null,
            'status'     => $primaryRole?->pivot?->status ?? 'active',
            'shift_type' => $assignedPivot?->shift_type,
            'started_at' => $assignedPivot?->created_at,
            'deleted_at' => $assignedPivot?->deleted_at, // Added deleted_at for frontend Boolean check
            'restaurants'=> $restaurants->toArray(),
        ];
    }

    // =========================================================================
    // API Global: Organisation level for Restaurant Owner
    // =========================================================================

    public function organizationIndex(Request $request)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json([
                'status'     => 'success',
                'stats'      => ['total' => 0, 'active' => 0],
                'data'       => [],
                'pagination' => null,
            ]);
        }

        $restaurantIds = Restaurant::where('organization_id', $ownedOrg->id)->pluck('id');

        $baseStaffIds = Staff::whereIn('restaurant_id', $restaurantIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('user_id');

        $stats = [
            'total'  => $baseStaffIds->count(),
            'active' => $baseStaffIds->isNotEmpty()
                ? DB::table('user_roles')
                    ->whereIn('user_id', $baseStaffIds)
                    ->where('status', 'active')
                    ->count()
                : 0,
        ];

        $query = User::select(['users.id', 'users.name', 'users.email'])
            ->with([
                'roles' => fn ($q) => $q->withPivot('status'),
                'assignedRestaurants' => fn ($q) => $q->whereIn('restaurants.id', $restaurantIds)
                    ->withPivot('shift_type', 'deleted_at') // Added deleted_at
            ]);

        // FIXED: Dynamically handle trashed / with_trashed filters
        if ($request->filled('trashed')) {
            $query->whereHas('assignedRestaurants', function ($q) use ($restaurantIds) {
                $q->whereIn('restaurants.id', $restaurantIds)
                  ->whereNotNull('staff.deleted_at');
            });
        } elseif ($request->filled('with_trashed')) {
            $query->whereHas('assignedRestaurants', function ($q) use ($restaurantIds) {
                $q->whereIn('restaurants.id', $restaurantIds);
            });
        } else {
            $query->whereHas('assignedRestaurants', function ($q) use ($restaurantIds) {
                $q->whereIn('restaurants.id', $restaurantIds)
                  ->whereNull('staff.deleted_at');
            });
        }

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->search;
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });

        $query->when($request->filled('role_id') || $request->filled('role'), function ($q) use ($request) {
            $roleInput = $request->role_id ?? $request->role;
            $q->whereHas('roles', function ($r) use ($roleInput) {
                $r->where(function ($sub) use ($roleInput) {
                    if (is_numeric($roleInput)) {
                        $sub->where('roles.id', (int) $roleInput);
                    } else {
                        $term = strtolower((string) $roleInput);
                        $sub->whereRaw('LOWER(roles.slug) = ?', [$term])
                            ->orWhereRaw('LOWER(roles.name) = ?', [$term]);
                    }
                });
            });
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->whereHas('roles', fn ($r) => $r->where('user_roles.status', $request->status));
        });

        $query->when($request->filled('shift_type'), function ($q) use ($restaurantIds, $request) {
            $q->whereHas('assignedRestaurants', fn ($r) => $r
                ->whereIn('restaurants.id', $restaurantIds)
                ->where('staff.shift_type', $request->shift_type));
        });

        $query->when($request->filled('restaurant_id'), function ($q) use ($request) {
            $q->whereHas('assignedRestaurants', fn ($r) => $r->where('restaurants.id', $request->restaurant_id));
        });

        $perPage   = $request->integer('per_page', 15);
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

    public function organizationStore(Request $request)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No organization found for this user.',
            ], 422);
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'password'       => 'nullable|string|min:8',
            'role_id'        => 'nullable',
            'role'           => 'nullable',
            'status'         => 'sometimes|string|in:active,suspended,on_leave,deactivated',
            'restaurant_ids' => 'required|array|min:1',
            'restaurant_ids.*' => 'integer|exists:restaurants,id',
            'shift_type'     => 'required|string|in:day,night,full_time,flexible',
        ]);

        $restaurantIds = Restaurant::where('organization_id', $ownedOrg->id)->pluck('id');
        $invalidRestaurants = collect($validated['restaurant_ids'])->diff($restaurantIds);
        
        if ($invalidRestaurants->isNotEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'One or more restaurants do not belong to your organization.',
            ], 403);
        }

        return DB::transaction(function () use ($validated, $ownedOrg) {
            $plainPassword = $validated['password'] ?? Str::random(12);

            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'     => $validated['name'],
                    'password' => Hash::make($plainPassword),
                ]
            );

            if ($user->name !== $validated['name']) {
                $user->update(['name' => $validated['name']]);
            }

            $status = $validated['status'] ?? 'active';
            $roleInput = $validated['role_id'] ?? $validated['role'] ?? 'staff';
            $role = $this->resolveRole($roleInput);

            if ($role) {
                $user->roles()->sync([
                    $role->id => ['status' => $status]
                ]);
            }

            foreach ($validated['restaurant_ids'] as $restaurantId) {
                $pivot = Staff::withTrashed()
                    ->where('user_id', $user->id)
                    ->where('restaurant_id', $restaurantId)
                    ->first();

                if ($pivot) {
                    if ($pivot->trashed()) {
                        $pivot->restore();
                    }
                    $pivot->update(['shift_type' => $validated['shift_type']]);
                } else {
                    Staff::create([
                        'user_id'       => $user->id,
                        'restaurant_id' => $restaurantId,
                        'shift_type'    => $validated['shift_type'],
                    ]);
                }
            }

            $firstRestaurant = Restaurant::find($validated['restaurant_ids'][0]);
            $user->notify(new StaffWelcomeNotification($plainPassword, $firstRestaurant));

            $user->load([
                'roles' => fn ($q) => $q->withPivot('status'),
                'assignedRestaurants' => fn ($q) => $q->whereIn('restaurants.id', $validated['restaurant_ids'])
                    ->withPivot('shift_type', 'deleted_at') // Added deleted_at
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Staff member created, assigned, and notified successfully.',
                'data'    => $this->formatStaffResponse($user),
            ], 201);
        });
    }

    public function organizationShow(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurantIds = Restaurant::where('organization_id', $ownedOrg->id)->pluck('id');

        $staff = User::select(['users.id', 'users.name', 'users.email'])
            ->with([
                'roles' => fn ($q) => $q->withPivot('status'),
                'assignedRestaurants' => fn ($q) => $q->whereIn('restaurants.id', $restaurantIds)
                    ->withPivot('shift_type', 'deleted_at') // Added deleted_at
            ])
            ->whereHas('assignedRestaurants', function ($q) use ($restaurantIds) {
                $q->whereIn('restaurants.id', $restaurantIds)
                  ->whereNull('staff.deleted_at');
            })
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatStaffResponse($staff),
        ]);
    }

    public function organizationUpdate(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurantIds = Restaurant::where('organization_id', $ownedOrg->id)->pluck('id');

        $staff = User::whereHas('assignedRestaurants', function ($q) use ($restaurantIds) {
            $q->whereIn('restaurants.id', $restaurantIds)
              ->whereNull('staff.deleted_at');
        })->findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => 'sometimes|email|max:255|unique:users,email,' . $id,
            'role_id'       => 'nullable',
            'role'          => 'nullable',
            'status'        => 'sometimes|string|in:active,suspended,on_leave,deactivated',
            'restaurant_id' => 'sometimes|integer|exists:restaurants,id',
            'shift_type'    => 'sometimes|string|in:day,night,full_time,flexible',
        ]);

        return DB::transaction(function () use ($staff, $validated, $restaurantIds) {
            $userUpdates = array_filter([
                'name'  => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
            ]);
            if (!empty($userUpdates)) {
                $staff->update($userUpdates);
            }

            $roleInput = $validated['role_id'] ?? $validated['role'] ?? null;
            $role      = $roleInput ? $this->resolveRole($roleInput) : $staff->roles()->first();

            if ($role) {
                $currentStatus = $staff->roles()->where('roles.id', $role->id)->first()?->pivot?->status ?? 'active';
                $newStatus     = $validated['status'] ?? $currentStatus;

                $staff->roles()->sync([
                    $role->id => ['status' => $newStatus]
                ]);
            }

            if (isset($validated['restaurant_id']) && isset($validated['shift_type'])) {
                if (! in_array($validated['restaurant_id'], $restaurantIds->toArray())) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Restaurant does not belong to your organization.',
                    ], 403);
                }
                
                Staff::where('user_id', $staff->id)
                    ->where('restaurant_id', $validated['restaurant_id'])
                    ->update(['shift_type' => $validated['shift_type']]);
            } elseif (isset($validated['shift_type'])) {
                Staff::where('user_id', $staff->id)
                    ->whereIn('restaurant_id', $restaurantIds)
                    ->update(['shift_type' => $validated['shift_type']]);
            }

            $staff->load([
                'roles' => fn ($q) => $q->withPivot('status'),
                'assignedRestaurants' => fn ($q) => $q->whereIn('restaurants.id', $restaurantIds)
                    ->withPivot('shift_type', 'deleted_at') // Added deleted_at
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Staff updated successfully.',
                'data'    => $this->formatStaffResponse($staff),
            ]);
        });
    }

    public function organizationDestroy(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurantIds = Restaurant::where('organization_id', $ownedOrg->id)->pluck('id');

        $validated = $request->validate([
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
        ]);

        $query = Staff::where('user_id', $id)
            ->whereIn('restaurant_id', $restaurantIds);

        if (isset($validated['restaurant_id'])) {
            if (! in_array($validated['restaurant_id'], $restaurantIds->toArray())) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Restaurant does not belong to your organization.',
                ], 403);
            }
            $query->where('restaurant_id', $validated['restaurant_id']);
        }

        $staffRecords = $query->get();

        if ($staffRecords->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Staff member not found in this organization.',
            ], 404);
        }

        foreach ($staffRecords as $staff) {
            $staff->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff removed from the specified restaurant(s) in this organization.',
        ]);
    }

    public function organizationRestore(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurantIds = Restaurant::where('organization_id', $ownedOrg->id)->pluck('id');

        $validated = $request->validate([
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
        ]);

        $query = Staff::onlyTrashed()
            ->where('user_id', $id)
            ->whereIn('restaurant_id', $restaurantIds);

        if (isset($validated['restaurant_id'])) {
            $query->where('restaurant_id', $validated['restaurant_id']);
        }

        $staffRecords = $query->get();

        if ($staffRecords->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Trashed staff member not found in this organization.',
            ], 404);
        }

        foreach ($staffRecords as $staff) {
            $staff->restore();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff restored to the specified restaurant(s) in this organization.',
        ]);
    }

    public function organizationForceDelete(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurantIds = Restaurant::where('organization_id', $ownedOrg->id)->pluck('id');

        $validated = $request->validate([
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
        ]);

        $query = Staff::withTrashed()
            ->where('user_id', $id)
            ->whereIn('restaurant_id', $restaurantIds);

        if (isset($validated['restaurant_id'])) {
            $query->where('restaurant_id', $validated['restaurant_id']);
        }

        $staffRecords = $query->get();

        if ($staffRecords->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Staff member not found in this organization.',
            ], 404);
        }

        foreach ($staffRecords as $staff) {
            $staff->forceDelete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff permanently removed from the specified restaurant(s) in this organization.',
        ]);
    }

    // =========================================================================
    // APIs inside restaurant for restaurant manager
    // =========================================================================

    public function index(Request $request)
    {
        $restaurant = $request->get('restaurant');

        $baseStaffIds = Staff::where('restaurant_id', $restaurant->id)
            ->whereNull('deleted_at')
            ->pluck('user_id');

        $stats = [
            'total'  => $baseStaffIds->count(),
            'active' => $baseStaffIds->isNotEmpty()
                ? DB::table('user_roles')
                    ->whereIn('user_id', $baseStaffIds)
                    ->where('status', 'active')
                    ->count()
                : 0,
        ];

        $query = User::select(['users.id', 'users.name', 'users.email'])
            ->with([
                'roles' => fn ($q) => $q->withPivot('status'),
                'assignedRestaurants' => fn ($q) => $q->where('restaurants.id', $restaurant->id)
                    ->withPivot('shift_type', 'deleted_at') // Added deleted_at
            ]);

        // FIXED: Dynamically handle trashed / with_trashed filters
        if ($request->filled('trashed')) {
            $query->whereHas('assignedRestaurants', function ($q) use ($restaurant) {
                $q->where('restaurants.id', $restaurant->id)
                  ->whereNotNull('staff.deleted_at');
            });
        } elseif ($request->filled('with_trashed')) {
            $query->whereHas('assignedRestaurants', function ($q) use ($restaurant) {
                $q->where('restaurants.id', $restaurant->id);
            });
        } else {
            $query->whereHas('assignedRestaurants', function ($q) use ($restaurant) {
                $q->where('restaurants.id', $restaurant->id)
                  ->whereNull('staff.deleted_at');
            });
        }

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->search;
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });

        $query->when($request->filled('role_id') || $request->filled('role'), function ($q) use ($request) {
            $roleInput = $request->role_id ?? $request->role;
            $q->whereHas('roles', function ($r) use ($roleInput) {
                $r->where(function ($sub) use ($roleInput) {
                    if (is_numeric($roleInput)) {
                        $sub->where('roles.id', (int) $roleInput);
                    } else {
                        $term = strtolower((string) $roleInput);
                        $sub->whereRaw('LOWER(roles.slug) = ?', [$term])
                            ->orWhereRaw('LOWER(roles.name) = ?', [$term]);
                    }
                });
            });
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->whereHas('roles', fn ($r) => $r->where('user_roles.status', $request->status));
        });

        $query->when($request->filled('shift_type'), function ($q) use ($restaurant, $request) {
            $q->whereHas('assignedRestaurants', fn ($r) => $r
                ->where('restaurants.id', $restaurant->id)
                ->where('staff.shift_type', $request->shift_type));
        });

        $perPage   = $request->integer('per_page', 15);
        $paginator = $query->latest('users.created_at')
            ->paginate($perPage)
            ->through(fn ($user) => $this->formatStaffResponse($user, $restaurant->id));

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

    public function store(Request $request)
    {
        $restaurant = $request->get('restaurant');

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'password'   => 'nullable|string|min:8',
            'role_id'    => 'nullable',
            'role'       => 'nullable',
            'status'     => 'sometimes|string|in:active,on_leave,deactivated',
            'shift_type' => 'required|string|in:day,night,full_time,flexible',
        ]);

        return DB::transaction(function () use ($validated, $restaurant) {
            $plainPassword = $validated['password'] ?? Str::random(12);

            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'     => $validated['name'],
                    'password' => Hash::make($plainPassword),
                ]
            );

            if ($user->name !== $validated['name']) {
                $user->update(['name' => $validated['name']]);
            }

            $status = $validated['status'] ?? 'active';

            $roleInput = $validated['role_id'] ?? $validated['role'] ?? 'staff';
            $role      = $this->resolveRole($roleInput);

            if ($role) {
                $user->roles()->sync([
                    $role->id => ['status' => $status]
                ]);
            }

            $pivot = Staff::withTrashed()
                ->where('user_id', $user->id)
                ->where('restaurant_id', $restaurant->id)
                ->first();

            $shiftType = $validated['shift_type'] ?? 'day';

            if ($pivot) {
                if ($pivot->trashed()) {
                    $pivot->restore();
                }
                $pivot->update(['shift_type' => $shiftType]);
            } else {
                Staff::create([
                    'user_id'       => $user->id,
                    'restaurant_id' => $restaurant->id,
                    'shift_type'    => $shiftType,
                ]);
            }

            $user->notify(new StaffWelcomeNotification($plainPassword, $restaurant));

            $user->load([
                'roles' => fn ($q) => $q->withPivot('status'),
                'assignedRestaurants' => fn ($q) => $q->where('restaurants.id', $restaurant->id)
                    ->withPivot('shift_type', 'deleted_at') // Added deleted_at
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Staff member created, assigned, and notified successfully.',
                'data'    => $this->formatStaffResponse($user, $restaurant->id)
            ], 201);
        });
    }

    public function show(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        $staff = User::select(['users.id', 'users.name', 'users.email'])
            ->with([
                'roles' => fn ($q) => $q->withPivot('status'),
                'assignedRestaurants' => fn ($q) => $q->where('restaurants.id', $restaurant->id)
                    ->withPivot('shift_type', 'deleted_at') // Added deleted_at
            ])
            ->whereHas('assignedRestaurants', function ($q) use ($restaurant) {
                $q->where('restaurants.id', $restaurant->id)
                  ->whereNull('staff.deleted_at');
            })
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatStaffResponse($staff, $restaurant->id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|max:255|unique:users,email,' . $id,
            'role_id'    => 'nullable',
            'role'       => 'nullable',
            'status'     => 'sometimes|string|in:active,on_leave,deactivated',
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

            $roleInput = $validated['role_id'] ?? $validated['role'] ?? null;
            $role      = $roleInput ? $this->resolveRole($roleInput) : $staff->roles()->first();

            if ($role) {
                $currentStatus = $staff->roles()->where('roles.id', $role->id)->first()?->pivot?->status ?? 'active';
                $newStatus     = $validated['status'] ?? $currentStatus;

                $staff->roles()->sync([
                    $role->id => ['status' => $newStatus]
                ]);
            }

            if (isset($validated['shift_type'])) {
                Staff::where('user_id', $staff->id)
                    ->where('restaurant_id', $restaurant->id)
                    ->update(['shift_type' => $validated['shift_type']]);
            }

            $staff->load([
                'roles' => fn ($q) => $q->withPivot('status'),
                'assignedRestaurants' => fn ($q) => $q->where('restaurants.id', $restaurant->id)
                    ->withPivot('shift_type', 'deleted_at') // Added deleted_at
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Staff updated successfully.',
                'data'    => $this->formatStaffResponse($staff, $restaurant->id),
            ]);
        });
    }

    public function destroy(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        $staff = Staff::where('user_id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (! $staff) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Staff member not found in this restaurant.',
            ], 404);
        }

        $staff->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff removed from this restaurant.',
        ]);
    }

    public function restore(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        $staff = Staff::onlyTrashed()
            ->where('user_id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (! $staff) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Trashed staff member not found.',
            ], 404);
        }

        $staff->restore();

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff restored to this restaurant.',
        ]);
    }

    public function forceDelete(Request $request, $id)
    {
        $restaurant = $request->get('restaurant');

        $staff = Staff::withTrashed()
            ->where('user_id', $id)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (! $staff) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Staff member not found.',
            ], 404);
        }

        $staff->forceDelete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff permanently removed from this restaurant.',
        ]);
    }
}