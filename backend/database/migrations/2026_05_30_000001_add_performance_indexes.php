<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the most critical query paths:
 *
 * - Public menu: restaurants WHERE active, catalogs WHERE restaurant_id AND active ORDER BY order,
 *   sections WHERE catalog_id AND active ORDER BY order,
 *   products WHERE section_id AND active, products WHERE restaurant_id AND active.
 * - Admin panel: user_restaurant WHERE restaurant_id (reversed lookup).
 * - Analytics: covered by existing indexes in analytics_events migration.
 * - Sanctum tokens: faster token lookups by tokenable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // restaurants: public listing filters on `active`; admin lookup uses `created_by`.
        Schema::table('restaurants', function (Blueprint $table) {
            $table->index('active', 'restaurants_active_idx');
            $table->index('created_by', 'restaurants_created_by_idx');
        });

        // catalogs: the hot query is restaurant_id + active + order.
        Schema::table('catalogs', function (Blueprint $table) {
            $table->index(['restaurant_id', 'active', 'order'], 'catalogs_restaurant_active_order_idx');
        });

        // sections: hot query is catalog_id + active + order.
        Schema::table('sections', function (Blueprint $table) {
            $table->index(['catalog_id', 'active', 'order'], 'sections_catalog_active_order_idx');
        });

        // products: two major hot paths —
        //   1. section_id + active (public menu render)
        //   2. restaurant_id + active (admin product counts / limit checks)
        Schema::table('products', function (Blueprint $table) {
            $table->index(['section_id', 'active'], 'products_section_active_idx');
            $table->index(['restaurant_id', 'active'], 'products_restaurant_active_idx');
        });

        // user_restaurant pivot: currently only the composite PK (user_id, restaurant_id) exists.
        // Looking up "all restaurants for a given restaurant_id" (used in syncAdmins / admins eager load)
        // requires scanning the full table without this index.
        Schema::table('user_restaurant', function (Blueprint $table) {
            $table->index('restaurant_id', 'user_restaurant_restaurant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex('restaurants_active_idx');
            $table->dropIndex('restaurants_created_by_idx');
        });

        Schema::table('catalogs', function (Blueprint $table) {
            $table->dropIndex('catalogs_restaurant_active_order_idx');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropIndex('sections_catalog_active_order_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_section_active_idx');
            $table->dropIndex('products_restaurant_active_idx');
        });

        Schema::table('user_restaurant', function (Blueprint $table) {
            $table->dropIndex('user_restaurant_restaurant_id_idx');
        });
    }
};
