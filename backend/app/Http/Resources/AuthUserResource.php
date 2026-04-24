<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AuthUserResource extends UserResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['requires_legal_acceptance'] = $this->terms_accepted_at === null || $this->privacy_accepted_at === null;
        $data['permissions'] = $this->permissions()->pluck('name');

        return $data;
    }
}
