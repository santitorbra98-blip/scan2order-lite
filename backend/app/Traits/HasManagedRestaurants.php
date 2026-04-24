<?php

namespace App\Traits;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;

trait HasManagedRestaurants
{
    public function managedRestaurantIds(): array
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        if ($this->hasRole('superadmin')) {
            return Restaurant::pluck('id')->all();
        }

        if ($this->hasRole('admin')) {
            return Restaurant::query()
                ->where(function (Builder $query) {
                    $query->whereHas('admins', function (Builder $adminQuery) {
                        $adminQuery->where('users.id', $this->id);
                    })->orWhere('created_by', $this->id);
                })
                ->pluck('id')
                ->all();
        }

        return [];
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
