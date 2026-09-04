<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    private function formatStaffResponse(User $user): array
    {
        $assignedPivot = $user->assignedRestaurants->first()?->pivot;
        $primaryRole   = $user->roles->first();

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
        ];
    }

    public function index(Request $request)
    {
        $restaurant = $request->get('restaurant');

        $baseStaffIds = Staff::where('restaurant_id', $restaurant->id)
            ->whereNull('deleted_at')
            ->pluck('user_id');

        $stats = [
            'total'  => $baseStaffIds->count(),
            'active' => DB::table('user_roles')
                ->whereIn('user_id', $baseStaffIds)
                ->where('status', 'active')
                ->count(),
        ];

        $query = User::select(['users.id', 'users.name', 'users.email'])
            ->with([
                'roles' => fn ($q) => $q->withPivot('status'),
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

            // Resolve role or assign fallback staff role
            $roleInput = $validated['role_id'] ?? $validated['role'] ?? 'staff';
            $role      = $this->resolveRole($roleInput);

            if ($role) {
                $user->roles()->sync([
                    $role->id => ['status' => $status]
                ]);
            }

            // Attach or Update Staff Pivot
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
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Staff member created, assigned, and notified successfully.',
                'data'    => $this->formatStaffResponse($user),
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
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Staff updated successfully.',
                'data'    => $this->formatStaffResponse($staff),
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