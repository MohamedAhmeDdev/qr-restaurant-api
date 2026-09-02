<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModifierGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifierGroupController extends Controller
{

public function option(Request $request): JsonResponse
{
    $restaurant = $request->attributes->get('restaurant');

    $groups = ModifierGroup::where('restaurant_id', $restaurant->id)
        ->select(['id', 'name', 'is_required', 'min_select', 'max_select'])
        ->with(['options:id,modifier_group_id,name,price,is_available'])
        ->orderBy('name')
        ->get();

    return response()->json([
        'status' => 'success',
        'data'   => $groups,
    ]);
}

  public function index(Request $request): JsonResponse
{
    $restaurant = $request->attributes->get('restaurant');

    $query = ModifierGroup::where('restaurant_id', $restaurant->id)
        ->with('options');

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhereHas('options', function ($optQuery) use ($search) {
                  $optQuery->where('name', 'like', "%{$search}%");
              });
        });
    }

    $perPage = $request->integer('per_page', 12);

    return response()->json([
        'status' => 'success',
        'data'   => $query->orderBy('name')->paginate($perPage),
    ]);
}

    public function store(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'description'            => 'nullable|string|max:1000',
            'min_select'             => 'nullable|integer|min:0|lte:max_select',
            'max_select'             => 'nullable|integer|min:1|gte:min_select',
            'is_required'            => 'nullable|boolean',
            'options'                => 'required|array|min:1',
            'options.*.name'         => 'required|string|max:255',
            'options.*.price'        => 'nullable|numeric|min:0',
            'options.*.is_available' => 'nullable|boolean',
        ]);

        $group = DB::transaction(function () use ($restaurant, $validated) {
            $group = ModifierGroup::create([
                'restaurant_id' => $restaurant->id,
                'name'          => $validated['name'],
                'description'   => $validated['description'] ?? null,
                'min_select'    => $validated['min_select'] ?? 0,
                'max_select'    => $validated['max_select'] ?? 1,
                'is_required'   => $validated['is_required'] ?? false,
            ]);

            foreach ($validated['options'] as $option) {
                $group->options()->create([
                    'name'         => $option['name'],
                    'price'        => $option['price'] ?? 0.00,
                    'is_available' => $option['is_available'] ?? true,
                ]);
            }

            return $group;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Modifier group created successfully.',
            'data'    => $group->load('options'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $group = ModifierGroup::where('restaurant_id', $restaurant->id)
            ->with('options')
            ->find($id);

        if (! $group) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Modifier group not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $group,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $group = ModifierGroup::where('restaurant_id', $restaurant->id)->find($id);

        if (! $group) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Modifier group not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name'                   => 'sometimes|string|max:255',
            'description'            => 'sometimes|nullable|string|max:1000',
            'min_select'             => 'sometimes|integer|min:0',
            'max_select'             => 'sometimes|integer|min:1',
            'is_required'            => 'sometimes|boolean',
            'options'                => 'sometimes|array',
            'options.*.id'           => 'nullable|integer',
            'options.*.name'         => 'required_with:options|string|max:255',
            'options.*.price'        => 'nullable|numeric|min:0',
            'options.*.is_available' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($group, $validated) {
            $group->update([
                'name'        => $validated['name'] ?? $group->name,
                'description' => $validated['description'] ?? $group->description,
                'min_select'  => $validated['min_select'] ?? $group->min_select,
                'max_select'  => $validated['max_select'] ?? $group->max_select,
                'is_required' => $validated['is_required'] ?? $group->is_required,
            ]);

            if (isset($validated['options'])) {
                $existingIds = $group->options()->pluck('id')->toArray();
                $sentIds = collect($validated['options'])->pluck('id')->filter()->toArray();

                // Safely delete removed options scoped strictly to this group
                $toDelete = array_diff($existingIds, $sentIds);
                if (! empty($toDelete)) {
                    $group->options()->whereIn('id', $toDelete)->delete();
                }

                foreach ($validated['options'] as $opt) {
                    if (! empty($opt['id'])) {
                        $group->options()->where('id', $opt['id'])->update([
                            'name'         => $opt['name'],
                            'price'        => $opt['price'] ?? 0.00,
                            'is_available' => $opt['is_available'] ?? true,
                        ]);
                    } else {
                        $group->options()->create([
                            'name'         => $opt['name'],
                            'price'        => $opt['price'] ?? 0.00,
                            'is_available' => $opt['is_available'] ?? true,
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Modifier group updated successfully.',
            'data'    => $group->load('options'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $group = ModifierGroup::where('restaurant_id', $restaurant->id)->find($id);

        if (! $group) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Modifier group not found.',
            ], 404);
        }

        $group->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Modifier group deleted successfully.',
        ]);
    }
}