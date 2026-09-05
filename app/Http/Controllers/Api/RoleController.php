<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleController extends Controller
{
   public function options(Request $request): JsonResponse
{
    $roles = Role::query()
        ->whereNotIn('slug', ['super_admin', 'restaurant_admin'])
        ->select(['id', 'name', 'slug', 'description'])
        ->orderBy('name')
        ->get();

    return response()->json([
        'status'  => 'success',
        'message' => 'Assignable roles retrieved successfully.',
        'data'    => $roles,
    ]);
}

    /**
     * List all roles with permission counts or relations.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::withCount('permissions')
            ->when($request->boolean('with_permissions'), function ($query) {
                $query->with('permissions:id,name,slug,group');
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Roles retrieved successfully.',
            'data' => $roles,
        ]);
    }

    /**
     * Store a new role and optionally sync initial permissions.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_system' => ['boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        // Generate a unique slug from the name on the backend
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $count = 1;

        while (Role::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $validated['slug'] = $slug;

        $role = DB::transaction(function () use ($validated) {
            $role = Role::create($validated);

            if (!empty($validated['permission_ids'])) {
                $role->permissions()->sync($validated['permission_ids']);
            }

            return $role;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully.',
            'data' => $role->load('permissions:id,name,slug'),
        ], 201);
    }

    /**
     * Show a specific role with attached permissions.
     */
  public function show(Request $request, Role $role): JsonResponse
    {
        // Load permissions and counts
        $role->load(['permissions' => function ($query) {
            $query->select('permissions.id', 'permissions.name', 'permissions.slug', 'permissions.group', 'permissions.description')
                  ->orderBy('group')
                  ->orderBy('name');
        }])->loadCount(['users', 'permissions']);

        // Option to structure permissions by group for easier permission assignment matrices
        if ($request->boolean('grouped') || $request->boolean('grouped_permissions')) {
            $role->grouped_permissions = $role->permissions->groupBy(fn($p) => $p->group ?: 'General');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Role retrieved successfully.',
            'data' => $role,
        ]);
    }

    /**
     * Update role details.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_system' => ['boolean'],
        ]);

        // Regenerate slug if name is updated
        if (isset($validated['name'])) {
            if ($role->is_system) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot modify the name/slug of a system role.',
                ], 422);
            }

            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $count = 1;

            while (Role::where('slug', $slug)->where('id', '!=', $role->id)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }

            $validated['slug'] = $slug;
        }

        $role->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully.',
            'data' => $role,
        ]);
    }

    /**
     * Sync permissions assigned to a role.
     */
 public function syncPermissions(Request $request, Role $role): JsonResponse
{
    $validated = $request->validate([
        'permission_ids'   => ['present', 'array'],
        'permission_ids.*' => ['integer', 'exists:permissions,id'],
    ]);

    $role->permissions()->sync($validated['permission_ids']);

    return response()->json([
        'status'  => 'success',
        'message' => 'Role permissions updated successfully.',
        'data'    => $role->load('permissions:id,name,slug,group'),
    ]);
}

    /**
     * Delete a role (protected for system roles).
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'status' => 'error',
                'message' => 'System roles cannot be deleted.',
            ], 403);
        }

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Role deleted successfully.',
        ]);
    }
}