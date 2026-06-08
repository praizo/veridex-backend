<?php

return [
    'redacted_value' => '[REDACTED]',

    'technical_log_retention_days' => env('TECHNICAL_LOG_RETENTION_DAYS', 30),
    'compliance_log_retention_days' => env('COMPLIANCE_LOG_RETENTION_DAYS', 2555),

    'nrs_raw_debug_export' => env('NRS_RAW_DEBUG_EXPORT', false),
    'nrs_raw_debug_disk' => env('NRS_RAW_DEBUG_DISK', 'local'),
    'nrs_raw_debug_path' => env('NRS_RAW_DEBUG_PATH', 'nrs-debug'),

    'sensitive_keys' => [
        'api_key',
        'api_secret',
        'x-api-key',
        'x-api-secret',
        'authorization',
        'bearer',
        'token',
        'access_token',
        'refresh_token',
        'password',
        'password_confirmation',
        'otp',
        'otp_code',
        'code',
        'secret',
        'raw_error',
        'street_name',
        'city_name',
        'postal_zone',
        'address',
        'postal_address',
    ],
];
