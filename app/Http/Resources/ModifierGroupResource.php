<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'description'    => $this->description ?? null,
            'min_selections' => (int) $this->min_selections,
            'max_selections' => (int) $this->max_selections,
            'is_required'    => $this->min_selections > 0,
            'options'        => ModifierOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}