<?php

namespace App\Http\Controllers;

use App\Jobs\LogAuditAction;
use App\Models\Restaurant;
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
        LogAuditAction::dispatch(
            $actor?->id,
            $targetUser?->id,
            $action,
            $resourceType,
            $resourceId !== null ? (string) $resourceId : null,
            $ipAddress,
            $userAgent,
            $metadata,
        );
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

    protected function authorizeRestaurant(int|string $restaurantId): Restaurant
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            abort(response()->json(['message' => 'No autenticado'], 401));
        }

        $restaurant = Restaurant::find($restaurantId);
        if (!$restaurant) {
            abort(response()->json(['message' => 'Restaurante no encontrado'], 404));
        }

        $this->authorize('manage', $restaurant);

        return $restaurant;
    }
}
