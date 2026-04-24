<?php

use Illuminate\Support\Facades\Log;

if (! function_exists('saveFallbackData')) {
    function saveFallbackData(array $data): void
    {
        $sensitiveKeys = ['password', 'password_hash', 'code', 'token', 'secret', 'phone', 'email'];
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($sensitiveKeys as $key) {
                if (array_key_exists($key, $data['data'])) {
                    $data['data'][$key] = '[REDACTED]';
                }
            }
        }

        Log::channel('db_errors')->warning('db_fallback.operation_failed', $data);
    }
}
