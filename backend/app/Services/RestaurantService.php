<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class RestaurantService
{
    private function imageDisk(): string
    {
        return config('filesystems.image_disk', 'public');
    }

    public function storeRestaurantImage($image): string
    {
        $disk      = $this->imageDisk();
        $imageName = Str::uuid()->toString() . '.jpg';
        $path      = 'restaurants/' . $imageName;

        // Compress: scale down to max 1200 px wide and encode as JPEG 80 %.
        $manager = new ImageManager(new GdDriver());
        $encoded = $manager->read($image->getRealPath())
            ->scaleDown(width: 1200)
            ->toJpeg(quality: 80);

        // Do NOT pass ['visibility' => 'public'] — R2 rejects per-object ACL headers.
        // Public access is controlled at bucket level in Cloudflare dashboard.
        $ok = Storage::disk($disk)->put($path, (string) $encoded);
        if ($ok === false) {
            throw new \RuntimeException('No se pudo guardar la imagen del restaurante.');
        }
        return $path;
    }

    public function deleteStoredRestaurantImage(?string $imagePath): void
    {
        if (!empty($imagePath)) {
            Storage::disk($this->imageDisk())->delete($imagePath);
        }
    }

    /**
     * Fully delete a restaurant: removes all product images, the restaurant
     * image, and hard-deletes the record so DB cascades clean up catalogs,
     * sections and products automatically.
     */
    public function deleteRestaurant(Restaurant $restaurant): void
    {
        // Delete all product images belonging to this restaurant
        $restaurant->products()->whereNotNull('image')->pluck('image')->each(
            fn (string $path) => Storage::disk($this->imageDisk())->delete($path)
        );

        // Delete the restaurant cover image
        $this->deleteStoredRestaurantImage($restaurant->image);

        // Force-delete triggers DB cascades: catalogs → sections → products
        $restaurant->forceDelete();
    }

    public function createRestaurant(array $data, User $creator, $imageFile = null): Restaurant
    {
        // Enforce per-admin restaurant limit (NULL = unlimited, superadmin is always unlimited)
        if ($creator->hasRole('admin') && !$creator->hasRole('superadmin')) {
            $limit = $creator->max_restaurants;
            if ($limit !== null) {
                $current = Restaurant::query()
                    ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($creator) {
                        $q->whereHas('admins', fn ($q2) => $q2->where('users.id', $creator->id))
                          ->orWhere('created_by', $creator->id);
                    })
                    ->count();

                if ($current >= $limit) {
                    throw new BusinessException(
                        "Has alcanzado el límite de {$limit} local(es) permitido(s) para tu cuenta.",
                        403
                    );
                }
            }
        }

        $data['created_by'] = $creator->id;

        try {
            if ($imageFile) {
                $data['image'] = $this->storeRestaurantImage($imageFile);
            }

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
            Log::error('Failed to create restaurant', ['exception' => $e->getMessage()]);

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
            Log::error('Failed to update restaurant', ['exception' => $e->getMessage()]);

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
