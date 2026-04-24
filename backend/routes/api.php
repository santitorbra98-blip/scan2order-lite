<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SetupController;
use Illuminate\Routing\Middleware\ThrottleRequests;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello from Laravel']);
})->withoutMiddleware([
    ThrottleRequests::class,
    'throttle:api',
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
]);

Route::get('/health', function () {
    // Keep health output minimal and protect it in production.
    if (app()->environment('production')) {
        $expectedToken = trim((string) env('HEALTH_CHECK_TOKEN', ''));
        if ($expectedToken === '') {
            return response()->json(['status' => 'unavailable'], 503);
        }

        $providedToken = (string) request()->header('X-Health-Token', request()->query('token', ''));
        if (!hash_equals($expectedToken, $providedToken)) {
            return response()->json(['status' => 'forbidden'], 403);
        }
    }

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response()->json(['status' => 'ok']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error'], 503);
    }
})->withoutMiddleware([
    ThrottleRequests::class,
    'throttle:api',
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
]);

// Setup endpoints (only available during initial setup - auto-disabled once 2 superadmins exist)
Route::post('/setup/create-superadmin', [SetupController::class, 'createSuperAdmin'])
    ->middleware('throttle:auth-login')
    ->withoutMiddleware(['throttle:api', \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class]);

// Legal information (public)
Route::get('/legal/meta', [LegalController::class, 'meta']);

// Auth routes (public)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register-request');
Route::post('/register/verify', [AuthController::class, 'verifyRegister'])->middleware('throttle:auth-register-verify');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-forgot-password');
Route::post('/verify-reset-code', [AuthController::class, 'verifyPasswordResetCode'])->middleware('throttle:auth-reset-password');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-reset-password');

// Protected auth routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/legal/acceptances', [LegalController::class, 'acceptances']);
});

// Public API endpoints (viewing menu)
Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])->whereNumber('restaurant');
Route::get('/restaurants/{restaurantId}/catalogs', [ProductController::class, 'getCatalogsByRestaurant']);

// Protected resource routes (admin only)
Route::middleware(['auth:sanctum'])->group(function () {
    // Restaurant management
    Route::get('/restaurants/stats', [ProductController::class, 'getRestaurantsStats']);
    Route::post('/restaurants', [RestaurantController::class, 'store']);
    Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
    Route::put('/restaurants/{restaurant}/admins', [RestaurantController::class, 'syncAdmins']);
    Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy']);

    // User management (superadmin only)
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/roles', [UserController::class, 'roles']);

    // Settings (superadmin only)
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);

    // Catalog management
    Route::get('/restaurants/{restaurantId}/catalogs/export-pdf', [ProductController::class, 'exportCatalogsPdf']);
    Route::post('/restaurants/{restaurantId}/catalogs', [ProductController::class, 'storeCatalog']);
    Route::put('/restaurants/{restaurantId}/catalogs/{catalogId}', [ProductController::class, 'updateCatalog']);
    Route::delete('/restaurants/{restaurantId}/catalogs/{catalogId}', [ProductController::class, 'deleteCatalog']);

    // Section management
    Route::post('/restaurants/{restaurantId}/catalogs/{catalogId}/sections', [ProductController::class, 'storeSection']);
    Route::put('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}', [ProductController::class, 'updateSection']);
    Route::delete('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}', [ProductController::class, 'deleteSection']);

    // Product management
    Route::post('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}/products', [ProductController::class, 'storeProduct']);
    Route::put('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}/products/{productId}', [ProductController::class, 'updateProduct']);
    Route::delete('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}/products/{productId}', [ProductController::class, 'deleteProduct']);
});
