<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description ?? null,
            'min_select'  => (int) $this->min_select,    // <-- Exact DB column
            'max_select'  => (int) $this->max_select,    // <-- Exact DB column
            'is_required' => (bool) $this->is_required,  // <-- Exact DB column
            'options'     => ModifierOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}