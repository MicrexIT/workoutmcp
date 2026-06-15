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
