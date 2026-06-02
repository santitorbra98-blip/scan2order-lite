<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that restricts access to users with all of the given permissions.
 *
 * Usage in routes:
 *   ->middleware('permission:manage_products')
 *   ->middleware('permission:manage_products,view_reports')
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        return $next($request);
    }
}
