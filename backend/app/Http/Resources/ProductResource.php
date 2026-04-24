<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'section_id' => $this->section_id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'show_image' => $this->show_image,
            'is_new' => $this->is_new,
            'allergens' => $this->allergens,
            'diet_tags' => $this->diet_tags,
            'price' => $this->price,
            'active' => $this->active,
            'section' => $this->whenLoaded('section', function () {
                return [
                    'id' => $this->section->id,
                    'name' => $this->section->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
