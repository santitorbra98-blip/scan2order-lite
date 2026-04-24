<?php

return [
    'mfa_email_code_ttl_minutes' => (int) env('MFA_EMAIL_CODE_TTL_MINUTES', 10),
    'mfa_email_max_attempts'     => (int) env('MFA_EMAIL_MAX_ATTEMPTS', 5),
    'audit_retention_days'       => (int) env('SECURITY_AUDIT_RETENTION_DAYS', 180),

    // Resource limits — prevent abuse and resource exhaustion by regular admins.
    // Superadmins bypass these limits. Configure via .env to change without code changes.
    'limits' => [
        'restaurants_per_admin'   => (int) env('LIMIT_RESTAURANTS_PER_ADMIN', 3),
        'catalogs_per_restaurant' => (int) env('LIMIT_CATALOGS_PER_RESTAURANT', 10),
        'sections_per_catalog'    => (int) env('LIMIT_SECTIONS_PER_CATALOG', 20),
        'products_per_section'    => (int) env('LIMIT_PRODUCTS_PER_SECTION', 100),
    ],
];
