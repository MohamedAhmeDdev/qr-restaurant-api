<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug ?? null,
            'description'  => $this->description,
            'price'        => (float) $this->price,
            'image_url'    => $this->image_url ?? null,
            'dietary_tags' => $this->dietary_tags ?? [],
            'is_available' => (bool) $this->is_available,
            'sort_order'   => (int) $this->sort_order,
            // Conditionally include modifiers when loaded via show()
            'modifier_groups' => ModifierGroupResource::collection($this->whenLoaded('modifierGroups')),
        ];
    }
}