<?php

namespace App\Support;

class CacheKeys
{
    /** TTLs in seconds. */
    public const CATALOG_TTL          = 300; // 5 min — public menu data
    public const PUBLIC_RESTAURANTS_TTL = 60;  // 1 min — active restaurant list
    public const ANALYTICS_TTL        = 300; // 5 min — analytics rankings

    public static function restaurantCatalogs(int $restaurantId): string
    {
        return "restaurant_{$restaurantId}_catalogs";
    }

    public static function publicRestaurants(): string
    {
        return 'public_restaurants';
    }
}
