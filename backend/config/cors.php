<?php

$corsOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173,http://localhost:3000')))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => !empty($corsOrigins) ? $corsOrigins : ['http://localhost:5173', 'http://localhost:3000'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Requested-With', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => true,
];
