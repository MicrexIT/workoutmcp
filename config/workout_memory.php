<?php

return [
    'single_user' => [
        'name' => env('WORKOUT_MEMORY_USER_NAME', 'Michele'),
        'email' => env('WORKOUT_MEMORY_USER_EMAIL', 'michele@example.com'),
        'timezone' => env('WORKOUT_MEMORY_TIMEZONE', 'Europe/Paris'),
        'preferred_weight_unit' => env('WORKOUT_MEMORY_WEIGHT_UNIT', 'kg'),
        'preferred_distance_unit' => env('WORKOUT_MEMORY_DISTANCE_UNIT', 'm'),
    ],

    'registration' => [
        'enabled' => (bool) env('WORKOUT_MEMORY_REGISTRATION_ENABLED', true),
    ],

    'rate_limits' => [
        'public_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_PUBLIC_PER_MINUTE', 240),
        'authenticated_web_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_AUTHENTICATED_WEB_PER_MINUTE', 120),
        'login_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_LOGIN_PER_MINUTE', 10),
        'login_email_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_LOGIN_EMAIL_PER_MINUTE', 5),
        'registration_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_REGISTRATION_PER_MINUTE', 5),
        'registration_per_hour' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_REGISTRATION_PER_HOUR', 20),
        'email_verification_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_EMAIL_VERIFICATION_PER_MINUTE', 6),
        'oauth_metadata_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_OAUTH_METADATA_PER_MINUTE', 120),
        'oauth_register_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_OAUTH_REGISTER_PER_MINUTE', 60),
        'oauth_register_per_hour' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_OAUTH_REGISTER_PER_HOUR', 300),
        'oauth_authorize_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_OAUTH_AUTHORIZE_PER_MINUTE', 60),
        'oauth_token_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_OAUTH_TOKEN_PER_MINUTE', 120),
        'oauth_token_client_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_OAUTH_TOKEN_CLIENT_PER_MINUTE', 60),
        'mcp_unauthenticated_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_MCP_UNAUTHENTICATED_PER_MINUTE', 600),
        'mcp_per_minute' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_MCP_PER_MINUTE', 240),
        'mcp_per_hour' => (int) env('WORKOUT_MEMORY_RATE_LIMIT_MCP_PER_HOUR', 5000),
    ],

    'company' => [
        'name' => env('WORKOUT_MEMORY_COMPANY_NAME', 'Remics Software Technologies - FZCO'),
    ],

    'support' => [
        'email' => env('WORKOUT_MEMORY_SUPPORT_EMAIL', 'michele@remics.tech'),
    ],

    'mcp_private_token' => env('MCP_PRIVATE_TOKEN'),

    'oauth' => [
        'public_url' => env('WORKOUT_MEMORY_PUBLIC_URL', env('APP_URL', 'http://localhost')),
        'issuer' => env('WORKOUT_MEMORY_OAUTH_ISSUER', env('WORKOUT_MEMORY_PUBLIC_URL', env('APP_URL', 'http://localhost'))),
        'authorization_code_ttl_minutes' => (int) env('WORKOUT_MEMORY_OAUTH_CODE_TTL_MINUTES', 10),
        'access_token_ttl_minutes' => (int) env('WORKOUT_MEMORY_OAUTH_ACCESS_TOKEN_TTL_MINUTES', 1440),
        'refresh_token_ttl_days' => (int) env('WORKOUT_MEMORY_OAUTH_REFRESH_TOKEN_TTL_DAYS', 30),
        'client_ttl_days' => (int) env('WORKOUT_MEMORY_OAUTH_CLIENT_TTL_DAYS', 30),
        'approval_ttl_days' => (int) env('WORKOUT_MEMORY_OAUTH_APPROVAL_TTL_DAYS', 365),
    ],
];
