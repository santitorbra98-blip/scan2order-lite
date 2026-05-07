<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /**
     * Determine whether the user can manage (create/update/delete catalogs, sections, products)
     * for the given restaurant.
     */
    public function manage(User $user, Restaurant $restaurant): bool
    {
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($user->hasRole('admin') && $user->hasPermission('manage_products')) {
            return $user->canAccessRestaurant($restaurant->id);
        }

        return false;
    }
}
