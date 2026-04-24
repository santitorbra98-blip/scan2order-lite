<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    /**
     * Create a superadmin user during initial setup (database must be completely empty).
     * This endpoint self-disables after the first user is created.
     * Security: Only works if no users exist at all in the database.
     */
    public function createSuperAdmin(Request $request)
    {
        // Only allow if database is completely empty (no users)
        $userCount = User::count();
        if ($userCount > 0) {
            return response()->json(
                ['message' => 'Database already has users. This endpoint is no longer available.'],
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
