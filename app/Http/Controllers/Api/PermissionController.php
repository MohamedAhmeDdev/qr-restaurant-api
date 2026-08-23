<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    /**
     * List permissions with optional search, group filtering, and structured grouping.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

        // Search by keyword across name, slug, or group
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('group', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by specific group
        if ($request->filled('group')) {
            $query->where('group', $request->query('group'));
        }

        $permissions = $query->orderBy('group')->orderBy('name')->get();

        // Extract clean array of unique groups
        $groups = $permissions->pluck('group')
            ->filter()
            ->unique()
            ->values();

        // Format response based on 'grouped' parameter
        if ($request->boolean('grouped')) {
            $groupedData = $permissions->groupBy(function ($item) {
                return $item->group ?: 'General';
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Grouped permissions retrieved successfully.',
                'groups' => $groups,
                'data' => $groupedData,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions retrieved successfully.',
            'groups' => $groups,
            'data' => $permissions,
        ]);
    }

    /**
     * Dedicated endpoint to fetch list of available group names.
     */
    public function getGroups(): JsonResponse
    {
        $groups = Permission::query()
            ->select('group')
            ->whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return response()->json([
            'status' => 'success',
            'message' => 'Permission groups retrieved successfully.',
            'data' => $groups,
        ]);
    }

    /**
     * Store a new permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['slug'] = $this->generateSlug(
            $validated['name'],
            $validated['group'] ?? null
        );

        $permission = Permission::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission created successfully.',
            'data' => $permission,
        ], 201);
    }

    /**
     * Show a specific permission.
     */
    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Permission retrieved successfully.',
            'data' => $permission->load('roles'),
        ]);
    }

    /**
     * Update an existing permission.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (array_key_exists('name', $validated) || array_key_exists('group', $validated)) {
            $name = $validated['name'] ?? $permission->name;
            $group = array_key_exists('group', $validated) ? $validated['group'] : $permission->group;

            $validated['slug'] = $this->generateSlug($name, $group, $permission->id);
        }

        $permission->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated successfully.',
            'data' => $permission,
        ]);
    }

    /**
     * Safely delete a permission and detach roles.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        DB::transaction(function () use ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Permission deleted successfully.',
        ]);
    }

    /**
     * Helper method to generate dot-notation slug with collision handling.
     */
    private function generateSlug(string $name, ?string $group = null, ?int $ignoreId = null): string
    {
        $actionSegment = Str::slug($name);

        if (!empty($group)) {
            $groupSegment = Str::slug($group);
            $baseSlug = "{$groupSegment}.{$actionSegment}";
        } else {
            $baseSlug = $actionSegment;
        }

        $slug = $baseSlug;
        $count = 1;

        while (
            Permission::where('slug', $slug)
                ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}.{$count}";
            $count++;
        }

        return $slug;
    }
}