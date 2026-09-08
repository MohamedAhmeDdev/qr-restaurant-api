<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    /**
     * View all soft-deleted entities for a restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $categories = Category::onlyTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->get();

        $menuItems = MenuItem::onlyTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->with('category:id,name')
            ->get();

        $modifierGroups = ModifierGroup::onlyTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'categories'      => $categories,
                'menu_items'      => $menuItems,
                'modifier_groups' => $modifierGroups,
            ],
        ]);
    }

    /**
     * Restore a specific trashed record.
     */
    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $modelMap = [
            'category'       => Category::class,
            'menu_item'      => MenuItem::class,
            'modifier_group' => ModifierGroup::class,
        ];

        if (! isset($modelMap[$type])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid entity type.'], 400);
        }

        $class = $modelMap[$type];
        $item = $class::onlyTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->find($id);

        if (! $item) {
            return response()->json(['status' => 'error', 'message' => 'Item not found in trash.'], 404);
        }

        $item->restore();

        return response()->json([
            'status'  => 'success',
            'message' => ucfirst(str_replace('_', ' ', $type)) . ' restored successfully.',
            'data'    => $item,
        ]);
    }

    /**
     * Permanently purge a specific trashed record.
     */
    public function forceDelete(Request $request, string $type, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $modelMap = [
            'category'       => Category::class,
            'menu_item'      => MenuItem::class,
            'modifier_group' => ModifierGroup::class,
        ];

        if (! isset($modelMap[$type])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid entity type.'], 400);
        }

        $class = $modelMap[$type];
        $item = $class::onlyTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->find($id);

        if (! $item) {
            return response()->json(['status' => 'error', 'message' => 'Item not found in trash.'], 404);
        }

        $item->forceDelete();

        return response()->json([
            'status'  => 'success',
            'message' => ucfirst(str_replace('_', ' ', $type)) . ' permanently deleted.',
        ]);
    }
}