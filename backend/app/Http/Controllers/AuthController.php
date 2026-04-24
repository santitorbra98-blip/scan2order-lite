<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthUserResource;
use App\Mail\MfaCodeMail;
use App\Mail\WelcomeMail;
use App\Models\EmailMfaCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $email = mb_strtolower(trim((string) $data['email']));

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json(['message' => 'El email ya está registrado'], 422);
        }

        $this->sendEmailRegistrationCode(
            email: $email,
            purpose: 'register',
            payload: [
                'name'   => (string) $data['name'],
                'phone'  => trim((string) ($data['phone'] ?? '')),
                'accept_terms' => (bool) $data['accept_terms'],
                'accept_privacy' => (bool) $data['accept_privacy'],
                'accept_marketing' => (bool) ($data['accept_marketing'] ?? false),
                'legal_version' => (string) config('legal.version'),
            ]
        );

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

                $entry = $this->consumeEmailRegistrationCode($email, 'register', (string) $data['code']);
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
            $this->sendEmailMfaCode($user, 'password_reset');
        }

        return response()->json([
            'message' => 'Si existe una cuenta con ese email, recibirás un código de verificación.',
        ]);
    }

    public function verifyPasswordResetCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'code' => 'required|string|max:12',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $normalizedCode = preg_replace('/\D+/', '', $data['code'] ?? '');

        if (!$normalizedCode || strlen($normalizedCode) !== 6) {
            return response()->json(['message' => 'Código inválido'], 422);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            return response()->json(['message' => 'No se encontró la cuenta asociada a ese email'], 422);
        }

        $valid = DB::transaction(function () use ($user, $normalizedCode) {
            $entry = EmailMfaCode::query()
                ->where('user_id', $user->id)
                ->where('purpose', 'password_reset')
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $maxAttempts = max(1, (int) config('security.mfa_email_max_attempts', 5));
            if (!$entry || $entry->attempts >= $maxAttempts) {
                return false;
            }

            if (!Hash::check($normalizedCode, $entry->code_hash)) {
                $entry->increment('attempts');
                return null;
            }

            return true;
        });

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
            'email' => 'required|string|email|max:255',
            'code' => 'required|string|max:12',
            'password' => 'required|string|min:12|confirmed',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            return response()->json(['message' => 'No se encontró la cuenta'], 422);
        }

        if (!$this->consumeEmailMfaCode($user, 'password_reset', (string) $data['code'])) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }

        $user->forceFill(['password' => $data['password']])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Contraseña restablecida correctamente']);
    }

    // ─── Private helpers ─────────────────────────────────────────

    private function sendEmailMfaCode(User $user, string $purpose): void
    {
        EmailMfaCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMinutes = max(1, (int) config('security.mfa_email_code_ttl_minutes', 10));

        EmailMfaCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'attempts' => 0,
        ]);

        $purposeLabel = match ($purpose) {
            'password_reset' => 'recuperación de contraseña',
            default => 'verificación',
        };

        Mail::to($user->email)->queue(new MfaCodeMail(
            code: $code,
            minutes: $ttlMinutes,
            purpose: $purposeLabel
        ));
    }

    private function sendEmailRegistrationCode(string $email, string $purpose, array $payload): void
    {
        EmailMfaCode::query()
            ->whereNull('user_id')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMinutes = max(1, (int) config('security.mfa_email_code_ttl_minutes', 10));

        EmailMfaCode::create([
            'user_id'    => null,
            'email'      => $email,
            'purpose'    => $purpose,
            'code_hash'  => Hash::make($code),
            'payload'    => $payload,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'attempts'   => 0,
        ]);

        Mail::to($email)->queue(new MfaCodeMail(
            code: $code,
            minutes: $ttlMinutes,
            purpose: 'completar tu registro'
        ));
    }

    private function consumeEmailRegistrationCode(string $email, string $purpose, string $code): ?EmailMfaCode
    {
        $normalizedCode = preg_replace('/\D+/', '', $code ?? '');
        if (!$normalizedCode || strlen($normalizedCode) !== 6) {
            return null;
        }

        return DB::transaction(function () use ($email, $purpose, $normalizedCode): ?EmailMfaCode {
            $entry = EmailMfaCode::query()
                ->whereNull('user_id')
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $maxAttempts = max(1, (int) config('security.mfa_email_max_attempts', 5));
            if (!$entry || $entry->attempts >= $maxAttempts) {
                return null;
            }

            if (!Hash::check($normalizedCode, $entry->code_hash)) {
                $entry->increment('attempts');
                return null;
            }

            $entry->update(['used_at' => now()]);
            return $entry;
        });
    }

    private function consumeEmailMfaCode(User $user, string $purpose, string $code): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code ?? '');
        if (!$normalizedCode || strlen($normalizedCode) !== 6) {
            return false;
        }

        return DB::transaction(function () use ($user, $purpose, $normalizedCode): bool {
            $entry = EmailMfaCode::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $maxAttempts = max(1, (int) config('security.mfa_email_max_attempts', 5));
            if (!$entry || $entry->attempts >= $maxAttempts) {
                return false;
            }

            if (!Hash::check($normalizedCode, $entry->code_hash)) {
                $entry->increment('attempts');
                return false;
            }

            $entry->update(['used_at' => now()]);
            return true;
        });
    }

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
