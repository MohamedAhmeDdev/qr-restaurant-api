<?php

namespace App\Http\Controllers\Api\public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicRestaurantController extends Controller
{
    /**
     * Get public details of a restaurant by its slug.
     * This is useful for loading branding/header before loading the full menu.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('is_active', true)
            ->where('status', 'active')
            ->first();

        if (! $restaurant) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Restaurant not found or currently unavailable.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'               => $restaurant->id,
                'name'             => $restaurant->name,
                'slug'             => $restaurant->slug,
                'logo'             => $restaurant->logo,
                'background_image' => $restaurant->background_image,
                'currency'         => $restaurant->currency ?? 'USD',
            ],
        ]);
    }
}