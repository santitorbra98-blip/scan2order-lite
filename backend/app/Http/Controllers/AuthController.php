<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthUserResource;
use App\Mail\WelcomeMail;
use App\Models\Role;
use App\Models\User;
use App\Services\MfaCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
class AuthController extends Controller
{
    public function __construct(private MfaCodeService $mfaService) {}

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $email = mb_strtolower(trim((string) $data['email']));

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json(['message' => 'El email ya está registrado'], 422);
        }

        try {
            $this->mfaService->sendToEmail(
                email: $email,
                purpose: 'register',
                payload: [
                    'name'             => (string) $data['name'],
                    'phone'            => trim((string) ($data['phone'] ?? '')),
                    'accept_terms'     => (bool) $data['accept_terms'],
                    'accept_privacy'   => (bool) $data['accept_privacy'],
                    'accept_marketing' => (bool) ($data['accept_marketing'] ?? false),
                    'legal_version'    => (string) config('legal.version'),
                ]
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'message' => 'Enviamos un código a tu email para completar el registro.',
            'verification_required' => true,
            'channel' => 'email',
            'email_hint' => $this->maskEmail($email),
        ], 202);
    }

    public function verifyRegister(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|string|email|max:255',
            'code'     => 'required|string|max:12',
            'password' => 'required|string|min:12|confirmed',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));

        try {
            $user = DB::transaction(function () use ($data, $email, $request) {
                if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['message' => 'El email ya está registrado'], 422)
                    );
                }

                $entry = $this->mfaService->verifyAndConsumeByEmail($email, 'register', (string) $data['code']);
                if (!$entry) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['message' => 'Código inválido o expirado'], 422)
                    );
                }

                $payload = is_array($entry->payload) ? $entry->payload : [];
                $name    = trim((string) ($payload['name'] ?? ''));

                if ($name === '') {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['message' => 'No pudimos completar el registro. Solicita un nuevo código.'], 422)
                    );
                }

                $assignedRole = Role::ensureDefault('admin');

                return User::create([
                    'name'    => $name,
                    'email'   => $email,
                    'phone'   => trim((string) ($payload['phone'] ?? '')) ?: null,
                    'password' => $data['password'],
                    'terms_accepted_at' => !empty($payload['accept_terms']) ? now() : null,
                    'privacy_accepted_at' => !empty($payload['accept_privacy']) ? now() : null,
                    'marketing_consent_at' => !empty($payload['accept_marketing']) ? now() : null,
                    'legal_version' => (string) ($payload['legal_version'] ?? config('legal.version')),
                    'legal_acceptance_ip' => $request->ip(),
                    'legal_acceptance_user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'role_id' => $assignedRole->id,
                    'status'  => 'active',
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $e->getResponse();
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json(['message' => 'El email ya está registrado'], 422);
        }

        $user->load('role');
        $token = $user->createToken('api-token')->plainTextToken;

        Mail::to($user->email)->queue(new WelcomeMail($user));

        $this->auditAction(
            actor: $user,
            action: 'auth.register_verified',
            resourceType: 'user',
            resourceId: $user->id,
            targetUser: $user,
            metadata: ['channel' => 'email'],
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent()
        );

        return response()->json([
            'message' => 'Registro completado correctamente.',
            'user' => new AuthUserResource($user),
            'token' => $token,
        ], 201);
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
