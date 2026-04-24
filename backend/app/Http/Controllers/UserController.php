<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasRole('superadmin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $users = User::with(['role', 'restaurants'])->orderBy('created_at', 'desc')->paginate(25);

        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasRole('superadmin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'phone'           => 'nullable|string|max:25',
            'password'        => 'required|string|min:12',
            'role_id'         => 'required|exists:roles,id',
            'status'          => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'max_restaurants' => 'nullable|integer|min:0|max:9999',
            'max_catalogs'    => 'nullable|integer|min:0|max:9999',
            'max_products'    => 'nullable|integer|min:0|max:9999',
        ]);

        $user = User::create([
            'name'            => $data['name'],
            'email'           => mb_strtolower(trim($data['email'])),
            'phone'           => trim($data['phone'] ?? '') ?: null,
            'password'        => $data['password'],
            'role_id'         => $data['role_id'],
            'status'          => $data['status'] ?? 'active',
            'created_by'      => $currentUser->id,
            'max_restaurants' => $data['max_restaurants'] ?? null,
            'max_catalogs'    => $data['max_catalogs'] ?? null,
            'max_products'    => $data['max_products'] ?? null,
        ]);

        $this->auditAction(
            actor: $currentUser,
            action: 'user.created',
            resourceType: 'user',
            resourceId: $user->id,
            targetUser: $user,
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent()
        );

        $user->load('role');

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasRole('superadmin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'No puedes modificar tu propia cuenta desde aquí'], 403);
        }

        $data = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'email'           => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'           => 'nullable|string|max:25',
            'password'        => 'nullable|string|min:12',
            'role_id'         => 'sometimes|exists:roles,id',
            'status'          => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
            'max_restaurants' => 'sometimes|nullable|integer|min:0|max:9999',
            'max_catalogs'    => 'sometimes|nullable|integer|min:0|max:9999',
            'max_products'    => 'sometimes|nullable|integer|min:0|max:9999',
        ]);

        $updateData = [];
        if (isset($data['name']))   $updateData['name'] = $data['name'];
        if (isset($data['email']))  $updateData['email'] = mb_strtolower(trim($data['email']));
        if (isset($data['phone']))  $updateData['phone'] = trim($data['phone']) ?: null;
        if (!empty($data['password'])) $updateData['password'] = $data['password'];
        if (isset($data['role_id'])) $updateData['role_id'] = $data['role_id'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (array_key_exists('max_restaurants', $data)) $updateData['max_restaurants'] = $data['max_restaurants'];
        if (array_key_exists('max_catalogs', $data))    $updateData['max_catalogs']    = $data['max_catalogs'];
        if (array_key_exists('max_products', $data))    $updateData['max_products']    = $data['max_products'];

        $user->update($updateData);
        $user->load('role');

        $this->auditAction(
            actor: $currentUser,
            action: 'user.updated',
            resourceType: 'user',
            resourceId: $user->id,
            targetUser: $user,
            metadata: array_keys($updateData),
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent()
        );

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user)
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasRole('superadmin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta'], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        $this->auditAction(
            actor: $currentUser,
            action: 'user.deleted',
            resourceType: 'user',
            resourceId: $user->id,
            targetUser: $user,
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent()
        );

        return response()->json(['message' => 'Usuario eliminado']);
    }

    public function roles(Request $request)
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasRole('superadmin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json(Role::select('id', 'name')->orderBy('name')->get());
    }
}
