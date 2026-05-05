<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    /**
     * Create a superadmin user during initial setup.
     *
     * Security layers:
     *  1. Requires the X-Setup-Token header to match the SETUP_TOKEN env variable.
     *     Without this, the endpoint is fully disabled (returns 404).
     *  2. Auto-disables once 2 superadmins already exist in the database.
     *
     * Intended for automated deployments (CI/CD). For interactive use prefer:
     *   php artisan superadmin:create
     */
    public function createSuperAdmin(Request $request)
    {
        // 1. Token gate — if SETUP_TOKEN is not configured or does not match, act as if the
        //    route does not exist (404) to avoid leaking that the endpoint is present.
        $setupToken = config('app.setup_token');
        if (empty($setupToken) || !hash_equals($setupToken, (string) $request->header('X-Setup-Token', ''))) {
            abort(404);
        }

        // 2. Only allow while fewer than 2 superadmins exist.
        //    After the second superadmin is created the endpoint auto-disables.
        $superadminCount = User::whereHas('role', fn ($q) => $q->where('name', 'superadmin'))->count();
        if ($superadminCount >= 2) {
            return response()->json(
                ['message' => 'Setup already completed. This endpoint is no longer available.'],
                403
            );
        }

        // Validate input
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|confirmed',
        ]);

        // Create superadmin
        $role = Role::ensureDefault('superadmin');
        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role_id' => $role->id,
            'status' => 'active',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Superadmin created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'superadmin',
            ],
        ], 201);
    }
}
