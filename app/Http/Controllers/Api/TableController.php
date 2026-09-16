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
use Illuminate\Validation\Rule;

class TableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');
        $baseQuery = Table::where('restaurant_id', $restaurant->id);

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'active'   => (clone $baseQuery)->where('is_active', true)->whereNull('deleted_at')->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->whereNull('deleted_at')->count(),
            'trash'    => (clone $baseQuery)->onlyTrashed()->count(),
        ];

        $query = clone $baseQuery;
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        } elseif ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } else {
            $query->whereNull('deleted_at');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('table_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);

        return response()->json([
            'status' => 'success',
            'stats'  => $stats,
            'data'   => $query->orderBy('table_number')->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $validated = $request->validate([
            'table_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('tables')->where('restaurant_id', $restaurant->id),
            ],
            'name'      => 'required|string|max:255',
            'capacity'  => 'nullable|integer|min:1|max:50',
            'is_active' => 'nullable|boolean',
        ]);

          if (Table::withTrashed()->where('restaurant_id', $restaurant->id)->where('name', $validated['name'])->exists()) {
        return response()->json([
            'status'  => 'error',
            'message' => 'A Table with this name already exists or has been previously deleted.',
        ], 422);
    }

        $slug  = $this->generateUniqueSlug($restaurant->id, $validated['name']);
        $token = Str::random(12);

        $table = Table::create([
            'restaurant_id' => $restaurant->id,
            'table_number'  => $validated['table_number'],
            'name'          => $validated['name'],
            'slug'          => $slug,
            'token'         => $token,
            'capacity'      => $validated['capacity'] ?? 2,
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
            'table_number' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::unique('tables')
                    ->where('restaurant_id', $restaurant->id)
                    ->ignore($table->id),
            ],
            'name'      => 'sometimes|string|max:255',
            'capacity'  => 'sometimes|integer|min:1|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $table->name) {
            $validated['slug'] = $this->generateUniqueSlug(
                $restaurant->id,
                $validated['name'],
                $table->id
            );

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


        public function restore(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $table = Table::onlyTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->find($id);

        if (! $table) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Trashed table not found.',
            ], 404);
        }

        $table->restore();

        return response()->json([
            'status'  => 'success',
            'message' => 'Table restored successfully.',
        ]);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $restaurant = $request->attributes->get('restaurant');

        $table = Table::withTrashed()
            ->where('restaurant_id', $restaurant->id)
            ->find($id);

        if (! $table) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Table not found.',
            ], 404);
        }

        $table->forceDelete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Table permanently deleted.',
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