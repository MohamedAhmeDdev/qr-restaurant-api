<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestaurantController extends Controller
{
    /**
     * Display a listing of restaurants belonging to the user's organization.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        $query = Restaurant::where('organization_id', $ownedOrg->id);

        // 1. Soft Deletes Filter
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        }

        // 2. Search Query (Name & Slug)
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->search;
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        });

        // 3. Status Filter (active / inactive / pending)
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    /**
     * Store a newly created restaurant.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json([
                'status' => 'error',
                'message' => 'No organization found for this user.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,suspended',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('restaurants/logos', 'public');
        }

        $slug = $this->generateUniqueSlug($validated['name']);

        $restaurant = Restaurant::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'organization_id' => $ownedOrg->id,
            'logo' => $logoPath ? Storage::url($logoPath) : null,
            'is_active' => $validated['is_active'] ?? true,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Restaurant created successfully.',
            'data' => $restaurant,
        ], 201);
    }

    /**
     * Show details of a specific restaurant under the user's organization.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurant = Restaurant::where('organization_id', $ownedOrg->id)->find($id);

        if (! $restaurant) {
            return response()->json(['status' => 'error', 'message' => 'Restaurant not found.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $restaurant->load('organization:id,name,slug'),
        ]);
    }

    /**
     * Update a restaurant under the user's organization.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurant = Restaurant::where('organization_id', $ownedOrg->id)->find($id);

        if (! $restaurant) {
            return response()->json(['status' => 'error', 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:active,suspended',
        ]);

        if ($request->hasFile('logo')) {
            if ($restaurant->logo) {
                $oldPath = str_replace('/storage/', '', $restaurant->logo);
                Storage::disk('public')->delete($oldPath);
            }

            $logoPath = $request->file('logo')->store('restaurants/logos', 'public');
            $validated['logo'] = Storage::url($logoPath);
        }

        if (isset($validated['name']) && $validated['name'] !== $restaurant->name) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $restaurant->id);
        }

        $restaurant->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Restaurant updated successfully.',
            'data' => $restaurant,
        ]);
    }

    /**
     * Soft delete a restaurant under the user's organization.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurant = Restaurant::where('organization_id', $ownedOrg->id)->find($id);

        if (! $restaurant) {
            return response()->json(['status' => 'error', 'message' => 'Restaurant not found.'], 404);
        }

        $restaurant->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Restaurant moved to trash (soft deleted).',
        ]);
    }

    /**
     * Restore a soft-deleted restaurant.
     */
    public function restore(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurant = Restaurant::onlyTrashed()
            ->where('organization_id', $ownedOrg->id)
            ->find($id);

        if (! $restaurant) {
            return response()->json(['status' => 'error', 'message' => 'Trashed restaurant not found.'], 404);
        }

        $restaurant->restore();

        return response()->json([
            'status' => 'success',
            'message' => 'Restaurant restored successfully.',
            'data' => $restaurant,
        ]);
    }

    /**
     * Permanently delete a restaurant.
     */
    public function forceDelete(Request $request, $id)
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        $restaurant = Restaurant::withTrashed()
            ->where('organization_id', $ownedOrg->id)
            ->find($id);

        if (! $restaurant) {
            return response()->json(['status' => 'error', 'message' => 'Restaurant not found.'], 404);
        }

        if ($restaurant->logo) {
            $oldPath = str_replace('/storage/', '', $restaurant->logo);
            Storage::disk('public')->delete($oldPath);
        }

        $restaurant->forceDelete();

        return response()->json([
            'status' => 'success',
            'message' => 'Restaurant permanently deleted.',
        ]);
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $count = 1;

        while (
            Restaurant::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}