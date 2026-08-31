<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $query = Table::where('restaurant_id', $restaurant->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);

        return response()->json([
            'status' => 'success',
            'data'   => $query->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'capacity'  => 'nullable|integer|min:1|max:50',
            'status'    => 'nullable|string|in:available,occupied,reserved,cleaning',
            'is_active' => 'nullable|boolean',
        ]);

        $slug  = $this->generateUniqueSlug($restaurant->id, $validated['name']);
        $token = Str::random(12);

        $table = Table::create([
            'restaurant_id' => $restaurant->id,
            'name'          => $validated['name'],
            'slug'          => $slug,
            'token'         => $token,
            'capacity'      => $validated['capacity'] ?? 2,
            'status'        => $validated['status'] ?? 'available',
            'is_active'     => $validated['is_active'] ?? true,
            'qr_code'       => $this->buildQrSvg($restaurant->slug, $slug, $token),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Table created successfully.',
            'data'    => $table,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $table = Table::where('restaurant_id', $restaurant->id)->find($id);

        if (! $table) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Table not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $table,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $table = Table::where('restaurant_id', $restaurant->id)->find($id);

        if (! $table) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Table not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'capacity'  => 'sometimes|integer|min:1|max:50',
            'status'    => 'sometimes|string|in:available,occupied,reserved,cleaning',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $table->name) {
            $validated['slug'] = $this->generateUniqueSlug(
                $restaurant->id,
                $validated['name'],
                $table->id
            );

            // Regenerate SVG so embedded URL matches the updated slug
    $validated['qr_code'] = $this->buildQrSvg(
        $restaurant->slug, 
        $validated['slug'], 
        $table->token
    );
        }

        $table->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Table updated successfully.',
            'data'    => $table,
        ]);
    }

    public function regenerateQr(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $table = Table::where('restaurant_id', $restaurant->id)->find($id);

        if (! $table) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Table not found.',
            ], 404);
        }

        $table->token = Str::random(12);
        $table->qr_code = $this->buildQrSvg($restaurant->slug, $table->slug, $table->token);
        $table->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'QR code regenerated successfully. Old physical code is now invalid.',
            'data'    => $table,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $table = Table::where('restaurant_id', $restaurant->id)->find($id);

        if (! $table) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Table not found.',
            ], 404);
        }

        $table->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Table deleted successfully.',
        ]);
    }

    private function buildQrSvg(string $restaurantSlug, string $tableSlug, string $token): string
    {
       $qrUrl = rtrim(config('app.frontend_url')) 
       . "/{$restaurantSlug}/menu/{$tableSlug}?token={$token}";

        $renderer = new ImageRenderer(
            new RendererStyle(300, 10),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($qrUrl);
    }



    private function generateUniqueSlug(int $restaurantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $count = 1;

        while (
            Table::where('restaurant_id', $restaurantId)
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