<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $query = MenuItem::where('restaurant_id', $restaurant->id)
            ->with(['category:id,name,slug', 'modifierGroups.options']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->boolean('only_available')) {
            $query->where('is_available', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);

        return response()->json([
            'status' => 'success',
            'data'   => $query->orderBy('sort_order')->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $validated = $request->validate([
            'category_id'       => 'required|integer|exists:categories,id',
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'price'             => 'required|numeric|min:0|max:999999.99',
            'image'             => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_available'      => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
            'sort_order'        => 'nullable|integer|min:0',
            'modifier_groups'   => 'nullable|array',
            'modifier_groups.*' => 'integer|exists:modifier_groups,id',
        ]);

        $category = $restaurant->categories()->find($validated['category_id']);
        if (! $category) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Category not found in this restaurant.',
            ], 404);
        }

        $slug = $this->generateUniqueSlug($restaurant->id, $validated['name']);

        $menuItem = DB::transaction(function () use ($restaurant, $validated, $slug, $request) {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store("restaurants/{$restaurant->id}/items", 'public');
            }

            $item = MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'category_id'   => $validated['category_id'],
                'name'          => $validated['name'],
                'slug'          => $slug,
                'description'   => $validated['description'] ?? null,
                'price'         => $validated['price'],
                'image'         => $imagePath ? Storage::url($imagePath) : null,
                'is_available'  => $validated['is_available'] ?? true,
                'is_active'     => $validated['is_active'] ?? true,
                'sort_order'    => $validated['sort_order'] ?? 0,
            ]);

            if (! empty($validated['modifier_groups'])) {
                $validGroupIds = ModifierGroup::where('restaurant_id', $restaurant->id)
                    ->whereIn('id', $validated['modifier_groups'])
                    ->pluck('id');

                $item->modifierGroups()->sync($validGroupIds);
            }

            return $item;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Menu item created successfully.',
            'data'    => $menuItem->load('modifierGroups.options'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)
            ->with(['category:id,name,slug', 'modifierGroups.options'])
            ->find($id);

        if (! $menuItem) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Menu item not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $menuItem,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->find($id);

        if (! $menuItem) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Menu item not found.',
            ], 404);
        }

        $validated = $request->validate([
            'category_id'       => 'sometimes|integer|exists:categories,id',
            'name'              => 'sometimes|string|max:255',
            'description'       => 'sometimes|nullable|string|max:2000',
            'price'             => 'sometimes|numeric|min:0|max:999999.99',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_available'      => 'sometimes|boolean',
            'is_active'         => 'sometimes|boolean',
            'sort_order'        => 'sometimes|integer|min:0',
            'modifier_groups'   => 'sometimes|array',
            'modifier_groups.*' => 'integer|exists:modifier_groups,id',
        ]);

        if (isset($validated['category_id'])) {
            $category = $restaurant->categories()->find($validated['category_id']);
            if (! $category) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Category not found in this restaurant.',
                ], 404);
            }
        }

        if (isset($validated['name']) && $validated['name'] !== $menuItem->name) {
            $validated['slug'] = $this->generateUniqueSlug(
                $restaurant->id,
                $validated['name'],
                $menuItem->id
            );
        }

        if ($request->hasFile('image')) {
            if ($menuItem->image) {
                $oldPath = str_replace('/storage/', '', $menuItem->image);
                Storage::disk('public')->delete($oldPath);
            }
            $imagePath = $request->file('image')->store("restaurants/{$restaurant->id}/items", 'public');
            $validated['image'] = Storage::url($imagePath);
        }

        $menuItem->update($validated);

        if (array_key_exists('modifier_groups', $validated)) {
            $validGroupIds = ModifierGroup::where('restaurant_id', $restaurant->id)
                ->whereIn('id', $validated['modifier_groups'] ?? [])
                ->pluck('id');

            $menuItem->modifierGroups()->sync($validGroupIds);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Menu item updated successfully.',
            'data'    => $menuItem->load('modifierGroups.options'),
        ]);
    }

    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');
        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->find($id);

        if (! $menuItem) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Menu item not found.',
            ], 404);
        }

        $menuItem->update(['is_active' => ! $menuItem->is_active]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Item status updated to ' . ($menuItem->is_active ? 'active' : 'inactive') . '.',
            'data'    => $menuItem,
        ]);
    }

    public function toggleAvailability(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');
        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->find($id);

        if (! $menuItem) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Menu item not found.',
            ], 404);
        }

        $menuItem->update(['is_available' => ! $menuItem->is_available]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Availability updated to ' . ($menuItem->is_available ? 'available' : 'sold out') . '.',
            'data'    => $menuItem,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->find($id);

        if (! $menuItem) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Menu item not found.',
            ], 404);
        }

        // NO Storage::disk('public')->delete() HERE:
        // Preserves stored image on soft-delete so it restores intact if recovered from trash.

        $menuItem->modifierGroups()->detach();
        $menuItem->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Menu item moved to trash successfully.',
        ]);
    }

    private function generateUniqueSlug(int $restaurantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $count = 1;

        while (
            MenuItem::where('restaurant_id', $restaurantId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }
}