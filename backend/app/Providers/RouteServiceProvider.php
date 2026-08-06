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
            return [
                // Per-(IP + credential): 8 attempts/min — stops casual brute force from one IP.
                Limit::perMinute(8)->by($login . '|' . $request->ip()),
                // Global per-credential: 20 attempts/5 min — stops distributed brute force across IPs.
                Limit::perMinutes(5, 20)->by('login:cred:' . $login),
            ];
        });

        RateLimiter::for('auth-register-request', function (Request $request) {
            return Limit::perMinutes(15, 4)->by($request->ip());
        });

        RateLimiter::for('auth-register-verify', function (Request $request) {
            return Limit::perMinutes(10, 8)->by($request->ip());
        });

        RateLimiter::for('contact-request', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'guest'));

            return [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perMinutes(60, 10)->by('contact:' . $request->ip() . '|' . $email),
            ];
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
