<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RestaurantService
{
    public function storeRestaurantImage($image): string
    {
        Storage::disk('public')->makeDirectory('restaurants');
        $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        $result = Storage::disk('public')->putFileAs('restaurants', $image, $imageName);
        if ($result === false) {
            throw new \RuntimeException('No se pudo guardar la imagen del restaurante.');
        }
        return 'restaurants/' . $imageName;
    }

    public function deleteStoredRestaurantImage(?string $imagePath): void
    {
        if (!empty($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    public function createRestaurant(array $data, User $creator, $imageFile = null): Restaurant
    {
        $data['created_by'] = $creator->id;

        if ($imageFile) {
            $data['image'] = $this->storeRestaurantImage($imageFile);
        }

        try {
            $restaurant = Restaurant::create($data);
            $restaurant->refresh();

            if ($creator->hasRole('admin')) {
                $adminRoleId = Role::where('name', 'admin')->value('id');
                if ($adminRoleId) {
                    $restaurant->users()->syncWithoutDetaching([
                        $creator->id => ['role_id' => $adminRoleId],
                    ]);
                }
            }

            return $restaurant;
        } catch (\Exception $e) {
            saveFallbackData(['action' => 'create_restaurant', 'data' => $data]);
            Log::channel('db_errors')->error('Failed to create restaurant', ['exception' => $e]);

            throw new BusinessException('Database error, operation saved for later', 500);
        }
    }

    public function updateRestaurant(Restaurant $restaurant, array $data, $imageFile = null, bool $removeImage = false): Restaurant
    {
        try {
            if ($removeImage && $restaurant->image) {
                $this->deleteStoredRestaurantImage($restaurant->image);
                $data['image'] = null;
            }

            if ($imageFile) {
                $this->deleteStoredRestaurantImage($restaurant->image);
                $data['image'] = $this->storeRestaurantImage($imageFile);
            }

            $restaurant->update($data);
            $restaurant->refresh();

            return $restaurant;
        } catch (\Exception $e) {
            saveFallbackData(['action' => 'update_restaurant', 'id' => $restaurant->id, 'data' => $data]);
            Log::channel('db_errors')->error('Failed to update restaurant', ['exception' => $e]);

            throw new BusinessException('Database error, operation saved for later', 500);
        }
    }

    public function syncAdmins(Restaurant $restaurant, array $adminIds, User $actingUser): Restaurant
    {
        $requestedAdminIds = collect($adminIds)->unique()->values()->all();

        if (count($requestedAdminIds) !== 1) {
            throw new BusinessException('Each restaurant must have exactly one admin', 422);
        }

        if ($actingUser->hasRole('admin') && !$actingUser->hasRole('superadmin')) {
            $allowedIds = [$actingUser->id];
            $hasForbiddenIds = count(array_diff($requestedAdminIds, $allowedIds)) > 0;
            if ($hasForbiddenIds) {
                throw new BusinessException('Admin can only assign themselves', 403);
            }
        }

        $validAdminIds = User::whereIn('id', $requestedAdminIds)
            ->whereHas('role', function ($query) {
                $query->where('name', 'admin');
            })
            ->pluck('id')
            ->all();

        if (count($validAdminIds) !== count($requestedAdminIds)) {
            throw new BusinessException('Some users are not admins', 422);
        }

        $adminRoleId = Role::where('name', 'admin')->value('id');
        if (!$adminRoleId) {
            throw new BusinessException('Admin role not found', 500);
        }

        $currentAdminIds = $restaurant->admins()->pluck('users.id')->all();
        $adminIdsToDetach = array_diff($currentAdminIds, $validAdminIds);
        if (!empty($adminIdsToDetach)) {
            $restaurant->users()->detach($adminIdsToDetach);
        }

        foreach ($validAdminIds as $adminId) {
            $restaurant->users()->syncWithoutDetaching([
                $adminId => ['role_id' => $adminRoleId],
            ]);
        }

        return $restaurant;
    }
}
