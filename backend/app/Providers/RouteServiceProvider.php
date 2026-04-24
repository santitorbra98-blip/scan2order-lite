<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $login = strtolower((string) $request->input('login', 'guest'));
            return Limit::perMinute(8)->by($login . '|' . $request->ip());
        });

        RateLimiter::for('auth-register-request', function (Request $request) {
            return Limit::perMinutes(15, 4)->by($request->ip());
        });

        RateLimiter::for('auth-register-verify', function (Request $request) {
            return Limit::perMinutes(10, 8)->by($request->ip());
        });

        RateLimiter::for('auth-forgot-password', function (Request $request) {
            return Limit::perMinutes(30, 3)->by('forgot:' . $request->ip());
        });

        RateLimiter::for('auth-reset-password', function (Request $request) {
            return Limit::perMinutes(15, 5)->by('reset:' . $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
