<?php

namespace App\Traits;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;

trait HasManagedRestaurants
{
    /** Per-instance cache so managedRestaurantIds() never hits the DB twice in one request. */
    private ?array $_cachedManagedRestaurantIds = null;

    public function managedRestaurantIds(): array
    {
        if ($this->_cachedManagedRestaurantIds !== null) {
            return $this->_cachedManagedRestaurantIds;
        }

        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        if ($this->hasRole('superadmin')) {
            return $this->_cachedManagedRestaurantIds = Restaurant::pluck('id')->all();
        }

        if ($this->hasRole('admin')) {
            return $this->_cachedManagedRestaurantIds = Restaurant::query()
                ->where(function (Builder $query) {
                    $query->whereHas('admins', function (Builder $adminQuery) {
                        $adminQuery->where('users.id', $this->id);
                    })->orWhere('created_by', $this->id);
                })
                ->pluck('id')
                ->all();
        }

        return $this->_cachedManagedRestaurantIds = [];
    }

    public function canAccessRestaurant(int $restaurantId): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        if ($this->hasRole('superadmin')) {
            return true;
        }

        return in_array($restaurantId, $this->managedRestaurantIds(), true);
    }
}
