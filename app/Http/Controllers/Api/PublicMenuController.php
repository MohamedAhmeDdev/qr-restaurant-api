<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MenuItemResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicMenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');
        $table = $request->attributes->get('table');

        $searchTerm = $request->query('q');

        $dietaryTags = [];
        if ($request->has('dietary_tags')) {
            $dietaryInput = $request->query('dietary_tags');
            $dietaryTags = is_array($dietaryInput)
                ? $dietaryInput
                : array_filter(explode(',', $dietaryInput));
        }

        $categories = Category::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->whereHas('menuItems', function ($query) use ($searchTerm, $dietaryTags) {
                $query->where('is_available', true)
                    ->where('is_active', true);

                if (! empty($searchTerm)) {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'like', "%{$searchTerm}%")
                          ->orWhere('description', 'like', "%{$searchTerm}%");
                    });
                }

                if (! empty($dietaryTags)) {
                    foreach ($dietaryTags as $tag) {
                        $query->whereJsonContains('dietary_tags', trim($tag));
                    }
                }
            })
            ->orderBy('sort_order')
            ->with([
                'menuItems' => function ($query) use ($searchTerm, $dietaryTags) {
                    $query->where('is_available', true)
                        ->where('is_active', true);

                    if (! empty($searchTerm)) {
                        $query->where(function ($q) use ($searchTerm) {
                            $q->where('name', 'like', "%{$searchTerm}%")
                              ->orWhere('description', 'like', "%{$searchTerm}%");
                        });
                    }

                    if (! empty($dietaryTags)) {
                        foreach ($dietaryTags as $tag) {
                            $query->whereJsonContains('dietary_tags', trim($tag));
                        }
                    }

                    $query->orderBy('sort_order');
                },
            ])
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'restaurant' => [
                    'id'               => $restaurant->id,
                    'name'             => $restaurant->name,
                    'slug'             => $restaurant->slug,
                    'logo'             => $restaurant->logo ?? null,
                    'background_image' => $restaurant->background_image ?? null,
                    'currency'         => $restaurant->currency ?? 'USD',
                ],
                'table' => [
                    'id'   => $table->id,
                    'name' => $table->name,
                    'slug' => $table->slug,
                ],
                'filters_applied' => [
                    'q'            => $searchTerm,
                    'dietary_tags' => array_values($dietaryTags),
                ],
                'categories' => CategoryResource::collection($categories),
            ],
        ]);
    }

    public function show(Request $request, int $itemId): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $menuItem = $restaurant->menuItems()
            ->where('is_active', true)
            ->where('is_available', true)
            ->with([
                'modifierGroups' => function ($mQuery) {
                    $mQuery->where('is_active', true)
                        ->with([
                            'options' => function ($oQuery) {
                                $oQuery->where('is_active', true)
                                    ->orderBy('sort_order');
                            },
                        ]);
                },
            ])
            ->find($itemId);

        if (! $menuItem) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Menu item not found or unavailable.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => new MenuItemResource($menuItem),
        ]);
    }
}