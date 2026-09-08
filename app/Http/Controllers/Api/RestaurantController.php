<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestaurantController extends Controller
{
    /**
     * Display a listing of restaurants belonging to the user's organization.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownedOrg = $user->ownedOrganizations()->first();

        if (! $ownedOrg) {
            return response()->json([
                'status' => 'success',
                'data'   => [],
            ]);
        }

        $query = Restaurant::where('organization_id', $ownedOrg->id);

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        }

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->search;
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        return response()->json([
            'status' => 'success',
            'data'   => $query->get(),
        ]);
    }

    /**
     * Store a newly created restaurant.
     */
    public function store(Request $request): JsonResponse
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
            'name'             => 'required|string|max:255',
            'logo'             => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'background_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
            'currency'         => 'required|string|size:3',
            'is_active'        => 'nullable|boolean',
            'status'           => 'nullable|string|in:active,suspended,pending',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('restaurants/logos', 'public');
        }

        $bgPath = null;
        if ($request->hasFile('background_image')) {
            $bgPath = $request->file('background_image')->store('restaurants/backgrounds', 'public');
        }

        $slug = $this->generateUniqueSlug($validated['name']);

        $logoUrl  = $logoPath ? Storage::url($logoPath) : null;
        $bgUrl    = $bgPath ? Storage::url($bgPath) : null;
        $currency = strtoupper($validated['currency']);

        $status = $validated['status'] ?? $this->determineOperationalStatus($logoUrl, $bgUrl, $currency);

        $restaurant = Restaurant::create([
            'name'             => $validated['name'],
            'slug'             => $slug,
            'organization_id'  => $ownedOrg->id,
            'logo'             => $logoUrl,
            'background_image' => $bgUrl,
            'currency'         => $currency,
            'is_active'        => $validated['is_active'] ?? true,
            'status'           => $status,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Restaurant created successfully.',
            'data'    => $restaurant,
        ], 201);
    }

    /**
     * Show details of a specific restaurant.
     */
    public function show(Request $request, int $id): JsonResponse
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
            'data'   => $restaurant->load('organization:id,name,slug'),
        ]);
    }

    /**
     * Update a restaurant.
     */
    public function update(Request $request, int $id): JsonResponse
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
            'name'             => 'sometimes|required|string|max:255',
            'logo'             => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
            'background_image' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:4096',
            'currency'         => 'sometimes|required|string|size:3',
            'is_active'        => 'sometimes|boolean',
            'status'           => 'sometimes|string|in:active,suspended,pending',
        ]);

        // Handle Logo Update
        if ($request->hasFile('logo')) {
            $this->deleteStoredFile($restaurant->logo);
            $logoPath = $request->file('logo')->store('restaurants/logos', 'public');
            $validated['logo'] = Storage::url($logoPath);
        }

        // Handle Background Image Update
        if ($request->hasFile('background_image')) {
            $this->deleteStoredFile($restaurant->background_image);
            $bgPath = $request->file('background_image')->store('restaurants/backgrounds', 'public');
            $validated['background_image'] = Storage::url($bgPath);
        }

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        if (isset($validated['name']) && $validated['name'] !== $restaurant->name) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $restaurant->id);
        }

        $restaurant->fill($validated);

        if ($restaurant->status !== 'suspended' && ! isset($validated['status'])) {
            $restaurant->status = $this->determineOperationalStatus(
                $restaurant->logo,
                $restaurant->background_image,
                $restaurant->currency
            );
        }

        $restaurant->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Restaurant updated successfully.',
            'data'    => $restaurant,
        ]);
    }

    /**
     * Soft delete a restaurant.
     */
    public function destroy(Request $request, int $id): JsonResponse
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

        // Soft delete restaurant - keep logos & backgrounds intact for potential restoration
        $restaurant->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Restaurant moved to trash (soft deleted).',
        ]);
    }

    /**
     * Restore a soft-deleted restaurant.
     */
    public function restore(Request $request, int $id): JsonResponse
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
            'status'  => 'success',
            'message' => 'Restaurant restored successfully.',
            'data'    => $restaurant,
        ]);
    }

    /**
     * Permanently delete a restaurant and remove stored images.
     */
    public function forceDelete(Request $request, int $id): JsonResponse
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

        // Clean up files from public disk
        $this->deleteStoredFile($restaurant->logo);
        $this->deleteStoredFile($restaurant->background_image);

        $restaurant->forceDelete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Restaurant permanently deleted.',
        ]);
    }

    /**
     * Operational status toggle (active <-> suspended)
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
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

        if ($request->has('status')) {
            $validated = $request->validate([
                'status' => 'required|string|in:active,suspended,pending',
            ]);
            $newStatus = $validated['status'];
        } else {
            $newStatus = ($restaurant->status === 'active') ? 'suspended' : 'active';
        }

        $restaurant->update(['status' => $newStatus]);

        return response()->json([
            'status'  => 'success',
            'message' => "Restaurant operational status updated to '{$newStatus}'.",
            'data'    => $restaurant,
        ]);
    }

    /**
     * Master activation toggle (is_active: true <-> false)
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $restaurant = Restaurant::withTrashed()->find($id);

        if (! $restaurant) {
            return response()->json(['status' => 'error', 'message' => 'Restaurant not found.'], 404);
        }

        if ($request->has('is_active')) {
            $validated = $request->validate([
                'is_active' => 'required|boolean',
            ]);
            $newActiveState = $validated['is_active'];
        } else {
            $newActiveState = ! $restaurant->is_active;
        }

        $restaurant->update(['is_active' => $newActiveState]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Restaurant ' . ($newActiveState ? 'activated' : 'deactivated') . '.',
            'data'    => $restaurant,
        ]);
    }

    /**
     * Evaluates whether required branding/configuration attributes are present.
     */
    private function determineOperationalStatus(?string $logo, ?string $bgImage, ?string $currency): string
    {
        if (empty($logo) || empty($bgImage) || empty($currency)) {
            return 'pending';
        }

        return 'active';
    }

    /**
     * Helper to purge a file using its full public URL.
     */
    private function deleteStoredFile(?string $url): void
    {
        if ($url) {
            $path = str_replace('/storage/', '', $url);
            Storage::disk('public')->delete($path);
        }
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