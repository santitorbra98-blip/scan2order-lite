<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalog_id' => $this->catalog_id,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'order' => $this->order,
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
