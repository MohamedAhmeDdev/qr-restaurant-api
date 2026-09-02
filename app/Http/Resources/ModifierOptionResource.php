<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'price'      => (float) $this->price,
            'is_default' => (bool) ($this->is_default ?? false),
            'sort_order' => (int) $this->sort_order,
        ];
    }
}