<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\MfaCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller
{
    public function __construct(private MfaCodeService $mfaService) {}

    public function register(Request $request)
    {
        return response()->json(['message' => 'El registro autónomo no está disponible. Solicita acceso desde el formulario de contacto.'], 404);
    }

    public function verifyRegister(Request $request)
    {
        return response()->json(['message' => 'El registro autónomo no está disponible. Solicita acceso desde el formulario de contacto.'], 404);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = $this->findUserByLogin((string) $credentials['login']);

        if (!$user || !Hash::check((string) $credentials['password'], (string) $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        $user->load('role');

        if ($user->status !== 'active') {
            return response()->json(['message' => 'La cuenta no está activa'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        $this->auditAction(
            actor: $user,
            action: 'auth.login',
            resourceType: 'user',
            resourceId: $user->id,
            targetUser: $user,
            metadata: ['role' => $user->role?->name],
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent()
        );

        return response()->json([
            'user' => new AuthUserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        $this->auditAction(
            actor: $request->user(),
            action: 'auth.logout',
            resourceType: 'user',
            resourceId: $request->user()?->id,
            targetUser: $request->user(),
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent()
        );

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('role');
        return new AuthUserResource($user);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            try {
                $this->mfaService->sendToUser($user, 'password_reset');
            } catch (\RuntimeException) {
                // swallow: always return the same generic response to avoid user enumeration
            }
        }

        return response()->json([
            'message' => 'Si existe una cuenta con ese email, recibirás un código de verificación.',
        ]);
    }

    public function verifyPasswordResetCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'code'  => 'required|string|max:12',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $user  = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }

        $valid = $this->mfaService->verifyOnly($user, 'password_reset', (string) $data['code']);

        if ($valid === false) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }
        if ($valid === null) {
            return response()->json(['message' => 'Código incorrecto'], 422);
        }

        return response()->json(['message' => 'Código válido']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|string|email|max:255',
            'code'     => 'required|string|max:12',
            'password' => 'required|string|min:12|confirmed',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $user  = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user || !$this->mfaService->verifyAndConsume($user, 'password_reset', (string) $data['code'])) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }

        $user->forceFill(['password' => $data['password']])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Contraseña restablecida correctamente']);
    }

    // ─── Private helpers ─────────────────────────────────────────

    private function findUserByLogin(string $login): ?User
    {
        $value = trim($login);
        if ($value === '') {
            return null;
        }

        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($value)])->first();
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $name = $parts[0];
        $domain = $parts[1];
        if (strlen($name) <= 2) {
            return '*' . '@' . $domain;
        }

        return substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2)) . '@' . $domain;
    }
}
