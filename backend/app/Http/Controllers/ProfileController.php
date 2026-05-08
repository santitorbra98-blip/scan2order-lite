<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthUserResource;
use App\Services\MfaCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(private MfaCodeService $mfaService) {}

    public function show(Request $request)
    {
        $user = $request->user('sanctum');
        $user->load('role');

        return new AuthUserResource($user);
    }

    public function update(Request $request)
    {
        $user = $request->user('sanctum');

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    // ─── Password change ──────────────────────────────────────────

    public function requestPasswordChange(Request $request)
    {
        $user = $request->user('sanctum');

        try {
            $this->mfaService->sendToUser($user, 'password_change');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'message' => 'Se ha enviado un código a tu correo para confirmar el cambio de contraseña.',
        ]);
    }

    public function confirmPasswordChange(Request $request)
    {
        $user = $request->user('sanctum');

        $data = $request->validate([
            'code'                  => 'required|string|max:12',
            'password'              => 'required|string|min:12|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if (!$this->mfaService->verifyAndConsume($user, 'password_change', $data['code'])) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }

        $user->forceFill(['password' => $data['password']])->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    // ─── Email change ─────────────────────────────────────────────

    public function requestEmailChange(Request $request)
    {
        $user = $request->user('sanctum');

        $data = $request->validate([
            'new_email' => 'required|email|max:255|unique:users,email',
        ]);

        $newEmail = mb_strtolower(trim((string) $data['new_email']));

        try {
            $this->mfaService->sendToUser($user, 'email_change', $newEmail, ['new_email' => $newEmail]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'message' => 'Se ha enviado un código al nuevo correo para confirmar el cambio.',
        ]);
    }

    public function confirmEmailChange(Request $request)
    {
        $user = $request->user('sanctum');

        $data = $request->validate([
            'code' => 'required|string|max:12',
        ]);

        $entry = $this->mfaService->verifyAndConsume($user, 'email_change', $data['code']);

        if (!$entry) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }

        $newEmail = $entry->payload['new_email'] ?? null;

        if (!$newEmail) {
            return response()->json(['message' => 'Error interno: email no encontrado en el código'], 500);
        }

        // Re-check uniqueness: another user may have taken it between request and confirm
        if (\App\Models\User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            return response()->json(['message' => 'El correo ya está en uso por otra cuenta'], 422);
        }

        $user->update(['email' => $newEmail]);

        return response()->json([
            'message' => 'Email actualizado correctamente',
            'email'   => $newEmail,
        ]);
    }

    // ─── Delete account ───────────────────────────────────────────

    public function deleteAccount(Request $request)
    {
        $user = $request->user('sanctum');

        $data = $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Contraseña incorrecta'], 422);
        }

        // Revoke all tokens before soft-deleting
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Cuenta eliminada correctamente']);
    }
}
