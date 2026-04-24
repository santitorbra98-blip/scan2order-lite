<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function auditAction(
        ?User $actor,
        string $action,
        ?string $resourceType = null,
        null|string|int $resourceId = null,
        ?User $targetUser = null,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
            AuditLog::create([
                'actor_user_id' => $actor?->id,
                'target_user_id' => $targetUser?->id,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId !== null ? (string) $resourceId : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // Do not block primary flow if audit storage fails.
        }
    }

    protected function managedRestaurantIds(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return $user->managedRestaurantIds();
    }

    protected function canAccessRestaurant(?User $user, int $restaurantId): bool
    {
        if (!$user) {
            return false;
        }

        return $user->canAccessRestaurant($restaurantId);
    }
}
