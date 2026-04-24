<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'secret',
        'authorization',
        'api_key',
        'code',
        'email',
        'phone',
    ];

    private const MAX_LOG_BODY_ITEMS = 200;

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $request = request();

            $context = [
                'user_id'   => $request->user()?->id,
                'url'       => $request->fullUrl(),
                'method'    => $request->method(),
                'ip'        => $request->ip(),
                'body'      => $this->sanitizeBody($request->all()),
                'exception' => get_class($e),
            ];

            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            if ($statusCode >= 500) {
                Log::critical($e->getMessage(), $context);
            }
        });
    }

    private function sanitizeBody(array $data): array
    {
        $sanitized = $this->sanitizeValue($data, null, 0);

        if (is_array($sanitized) && count($sanitized) > self::MAX_LOG_BODY_ITEMS) {
            return [
                '_warning' => 'payload_truncated',
                '_size' => count($sanitized),
            ];
        }

        return is_array($sanitized) ? $sanitized : [];
    }

    private function sanitizeValue(mixed $value, ?string $key, int $depth): mixed
    {
        if ($depth > 8) {
            return '[TRUNCATED_DEPTH]';
        }

        if ($key !== null && $this->isSensitiveKey($key)) {
            return '***REDACTED***';
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $childKey => $childValue) {
                $result[$childKey] = $this->sanitizeValue(
                    $childValue,
                    is_string($childKey) ? $childKey : null,
                    $depth + 1
                );
            }
            return $result;
        }

        if (is_string($value) && strlen($value) > 2048) {
            return substr($value, 0, 2048) . '...[TRUNCATED]';
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = mb_strtolower(trim($key));
        foreach (self::SENSITIVE_FIELDS as $field) {
            if ($normalized === $field || str_contains($normalized, $field)) {
                return true;
            }
        }

        return false;
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Unauthenticated.'], 401)
            : redirect()->guest('/login');
    }
}
