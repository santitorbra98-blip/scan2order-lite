<?php

namespace App\Support;

class CacheKeys
{
    public static function restaurantCatalogs(int $restaurantId): string
    {
        return "restaurant_{$restaurantId}_catalogs";
    }
}
