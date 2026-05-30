<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\AnalyticsController;
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

    // Profile management
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/request-password-change', [ProfileController::class, 'requestPasswordChange'])->middleware('throttle:auth-forgot-password');
    Route::post('/profile/confirm-password-change', [ProfileController::class, 'confirmPasswordChange'])->middleware('throttle:auth-reset-password');
    Route::post('/profile/request-email-change', [ProfileController::class, 'requestEmailChange'])->middleware('throttle:auth-forgot-password');
    Route::post('/profile/confirm-email-change', [ProfileController::class, 'confirmEmailChange'])->middleware('throttle:auth-reset-password');
    Route::delete('/profile', [ProfileController::class, 'deleteAccount']);
});

// Analytics - public endpoints
// 30 writes/min per IP prevents analytics abuse; top-restaurants is read-only so 60/min is fine.
Route::post('/analytics/event', [AnalyticsController::class, 'trackEvent'])->middleware('throttle:30,1');
Route::get('/analytics/top-restaurants', [AnalyticsController::class, 'topRestaurants'])->middleware('throttle:30,1');

// Public API endpoints (viewing menu)
Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])->whereNumber('restaurant');
Route::get('/restaurants/{restaurantId}/catalogs', [CatalogController::class, 'getCatalogsByRestaurant']);

// Protected resource routes (admin + superadmin)
Route::middleware(['auth:sanctum', 'role:admin,superadmin'])->group(function () {
    // Restaurant management
    Route::get('/restaurants/stats', [CatalogController::class, 'getRestaurantsStats']);
    Route::post('/restaurants', [RestaurantController::class, 'store']);
    Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
    Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy']);

    // Catalog management
    Route::get('/restaurants/{restaurantId}/catalogs/export-pdf', [CatalogController::class, 'exportCatalogsPdf']);
    Route::post('/restaurants/{restaurantId}/catalogs/import-json', [CatalogController::class, 'importJson']);
    Route::post('/restaurants/{restaurantId}/catalogs', [CatalogController::class, 'storeCatalog']);
    Route::put('/restaurants/{restaurantId}/catalogs/{catalogId}', [CatalogController::class, 'updateCatalog']);
    Route::delete('/restaurants/{restaurantId}/catalogs/{catalogId}', [CatalogController::class, 'deleteCatalog']);

    // Section management
    Route::post('/restaurants/{restaurantId}/catalogs/{catalogId}/sections', [CatalogController::class, 'storeSection']);
    Route::put('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}', [CatalogController::class, 'updateSection']);
    Route::delete('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}', [CatalogController::class, 'deleteSection']);

    // Product management
    Route::post('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}/products', [ProductController::class, 'storeProduct']);
    Route::put('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}/products/{productId}', [ProductController::class, 'updateProduct']);
    Route::delete('/restaurants/{restaurantId}/catalogs/{catalogId}/sections/{sectionId}/products/{productId}', [ProductController::class, 'deleteProduct']);
});

// Superadmin-only routes
Route::middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
    // User management
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/roles', [UserController::class, 'roles']);

    // Analytics
    Route::get('/analytics/ranking', [AnalyticsController::class, 'ranking']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);
});
