<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $query = Category::where('restaurant_id', $restaurant->id);

        if ($request->boolean('only_active')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 50);

        return response()->json([
            'status' => 'success',
            'data'   => $query->orderBy('sort_order')->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $slug = $this->generateUniqueSlug($restaurant->id, $validated['name']);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => $validated['name'],
            'slug'          => $slug,
            'description'   => $validated['description'] ?? null,
            'sort_order'    => $validated['sort_order'] ?? 0,
            'is_active'     => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Category created successfully.',
            'data'    => $category,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $category = Category::where('restaurant_id', $restaurant->id)->find($id);

        if (! $category) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Category not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $category,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $category = Category::where('restaurant_id', $restaurant->id)->find($id);

        if (! $category) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Category not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'sort_order'  => 'sometimes|integer|min:0',
            'is_active'   => 'sometimes|boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $validated['slug'] = $this->generateUniqueSlug(
                $restaurant->id,
                $validated['name'],
                $category->id
            );
        }

        $category->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Category updated successfully.',
            'data'    => $category,
        ]);
    }




public function reorder(Request $request): JsonResponse
{
    $restaurant = $request->attributes->get('restaurant');

    $validated = $request->validate([
        'orders'              => 'required|array',
        'orders.*.id'         => 'required|integer',
        'orders.*.sort_order' => 'required|integer|min:0',
    ]);

    DB::transaction(function () use ($restaurant, $validated) {
        foreach ($validated['orders'] as $item) {
            Category::where('restaurant_id', $restaurant->id)
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }
    });

    return response()->json([
        'status'  => 'success',
        'message' => 'Categories reordered successfully.',
    ]);
}
    
    public function destroy(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $category = Category::where('restaurant_id', $restaurant->id)->find($id);

        if (! $category) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Category not found.',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Category deleted successfully.',
        ]);
    }

    private function generateUniqueSlug(int $restaurantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $count = 1;

        while (
            Category::where('restaurant_id', $restaurantId)
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