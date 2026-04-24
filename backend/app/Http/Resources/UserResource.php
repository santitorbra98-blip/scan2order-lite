<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'role' => $this->whenLoaded('role', function () {
                return [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                ];
            }),
            'restaurants' => $this->whenLoaded('restaurants', function () {
                return $this->restaurants->map(fn ($r) => [
                    'id'   => $r->id,
                    'name' => $r->name,
                ]);
            }),
            'max_restaurants' => $this->max_restaurants,
            'max_catalogs'    => $this->max_catalogs,
            'max_products'    => $this->max_products,
            'avatar' => $this->avatar,
            'terms_accepted_at' => $this->terms_accepted_at?->toIso8601String(),
            'privacy_accepted_at' => $this->privacy_accepted_at?->toIso8601String(),
            'marketing_consent_at' => $this->marketing_consent_at?->toIso8601String(),
            'legal_version' => $this->legal_version,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
