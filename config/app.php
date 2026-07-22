<?php

declare(strict_types=1);

/**
 * Core application configuration.
 * Values are resolved from environment variables with safe defaults.
 */
return [
    'name'     => env('APP_NAME', 'Paragon HostOps'),
    'tagline'  => 'Hosting Management & Operations Platform',
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env_bool('APP_DEBUG', false),
    'url'      => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Africa/Johannesburg'),
    'key'      => env('APP_KEY', ''),

    'session' => [
        'lifetime' => (int) env('SESSION_LIFETIME', 120),
        // Secure cookies default ON in production; can be overridden by the env
        // var (e.g. a staging box on plain HTTP).
        'secure_cookie' => env_bool('SESSION_SECURE_COOKIE', env('APP_ENV', 'production') === 'production'),
        'same_site'     => env('SESSION_SAME_SITE', 'Lax'),
        'name'          => 'paragon_hostops_session',
        'path'          => __DIR__ . '/../storage/sessions',
    ],

    'security' => [
        'login_max_attempts'    => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
        'cron_secret'           => env('CRON_SECRET', ''),
    ],
];
