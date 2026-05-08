<?php

namespace App\Services;

use App\Mail\MfaCodeMail;
use App\Models\EmailMfaCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class MfaCodeService
{
    /**
     * Send an MFA code to a user.
     * Supports sending to a different email (e.g. email_change) and storing extra payload.
     */
    public function sendToUser(User $user, string $purpose, ?string $sendToEmail = null, array $payload = []): void
    {
        $email      = $sendToEmail ?? $user->email;
        $code       = $this->generateCode();
        $ttlMinutes = $this->ttl();

        EmailMfaCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        EmailMfaCode::create([
            'user_id'    => $user->id,
            'purpose'    => $purpose,
            'code_hash'  => Hash::make($code),
            'payload'    => empty($payload) ? null : $payload,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'attempts'   => 0,
        ]);

        try {
            Mail::to($email)->queue(new MfaCodeMail(
                code: $code,
                minutes: $ttlMinutes,
                purpose: $this->purposeLabel($purpose),
            ));
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo enviar el email. Por favor, inténtalo de nuevo más tarde.', 0, $e);
        }
    }

    /**
     * Send an MFA code to an unregistered email (registration flow).
     */
    public function sendToEmail(string $email, string $purpose, array $payload = []): void
    {
        $code       = $this->generateCode();
        $ttlMinutes = $this->ttl();

        EmailMfaCode::whereNull('user_id')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        EmailMfaCode::create([
            'user_id'    => null,
            'email'      => $email,
            'purpose'    => $purpose,
            'code_hash'  => Hash::make($code),
            'payload'    => empty($payload) ? null : $payload,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'attempts'   => 0,
        ]);

        try {
            Mail::to($email)->queue(new MfaCodeMail(
                code: $code,
                minutes: $ttlMinutes,
                purpose: $this->purposeLabel($purpose),
            ));
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo enviar el email. Por favor, inténtalo de nuevo más tarde.', 0, $e);
        }
    }

    /**
     * Verify a user-bound code without consuming it.
     * Returns true on valid, null on wrong code, false on invalid/expired/locked.
     */
    public function verifyOnly(User $user, string $purpose, string $code): bool|null
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === null) {
            return false;
        }

        return DB::transaction(function () use ($user, $purpose, $normalized): bool|null {
            $maxAttempts = max(1, (int) config('security.mfa_email_max_attempts', 5));

            $entry = EmailMfaCode::where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$entry || $entry->attempts >= $maxAttempts) {
                return false;
            }

            if (!Hash::check($normalized, $entry->code_hash)) {
                $entry->increment('attempts');
                return null;
            }

            return true;
        });
    }

    /**
     * Verify and consume a user-bound code.
     * Returns the record on success, null on failure.
     */
    public function verifyAndConsume(User $user, string $purpose, string $code): ?EmailMfaCode
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === null) {
            return null;
        }

        return DB::transaction(function () use ($user, $purpose, $normalized): ?EmailMfaCode {
            $maxAttempts = max(1, (int) config('security.mfa_email_max_attempts', 5));

            $entry = EmailMfaCode::where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$entry || $entry->attempts >= $maxAttempts) {
                return null;
            }

            if (!Hash::check($normalized, $entry->code_hash)) {
                $entry->increment('attempts');
                return null;
            }

            $entry->update(['used_at' => now()]);
            return $entry;
        });
    }

    /**
     * Verify and consume an email-bound code (registration flow).
     * Returns the record on success, null on failure.
     */
    public function verifyAndConsumeByEmail(string $email, string $purpose, string $code): ?EmailMfaCode
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === null) {
            return null;
        }

        return DB::transaction(function () use ($email, $purpose, $normalized): ?EmailMfaCode {
            $maxAttempts = max(1, (int) config('security.mfa_email_max_attempts', 5));

            $entry = EmailMfaCode::whereNull('user_id')
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$entry || $entry->attempts >= $maxAttempts) {
                return null;
            }

            if (!Hash::check($normalized, $entry->code_hash)) {
                $entry->increment('attempts');
                return null;
            }

            $entry->update(['used_at' => now()]);
            return $entry;
        });
    }

    // ─── Private helpers ─────────────────────────────────────────

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function ttl(): int
    {
        return max(1, (int) config('security.mfa_email_code_ttl_minutes', 10));
    }

    private function normalizeCode(string $code): ?string
    {
        $normalized = preg_replace('/\D+/', '', $code);
        return ($normalized && strlen($normalized) === 6) ? $normalized : null;
    }

    private function purposeLabel(string $purpose): string
    {
        return match ($purpose) {
            'password_reset'  => 'recuperación de contraseña',
            'password_change' => 'cambio de contraseña',
            'email_change'    => 'cambio de email',
            'register'        => 'completar tu registro',
            default           => 'verificación',
        };
    }
}
