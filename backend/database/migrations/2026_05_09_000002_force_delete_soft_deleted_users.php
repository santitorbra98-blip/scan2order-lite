<?php

use App\Models\Restaurant;
use App\Models\User;
use App\Services\RestaurantService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Permanently remove users that were previously soft-deleted so their
     * email and phone are freed from the UNIQUE constraints.
     * Also cleans up any restaurants they created (including images).
     */
    public function up(): void
    {
        $restaurantService = app(RestaurantService::class);

        User::onlyTrashed()->get()->each(function (User $user) use ($restaurantService) {
            // Delete restaurants created by this user
            Restaurant::withTrashed()
                ->where('created_by', $user->id)
                ->get()
                ->each(fn (Restaurant $r) => $restaurantService->deleteRestaurant($r));

            $user->tokens()->delete();
            $user->forceDelete();
        });
    }

    public function down(): void
    {
        // Irreversible — data was already deleted
    }
};
