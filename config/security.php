<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'session' => [
        'idle'     => (int) Env::get('SESSION_IDLE', 30),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 120),
    ],
    'login' => [
        'max_attempts'    => 5,
        'decay_minutes'   => 15,
    ],
    'rate_limits' => [
        // key => [max requests, per seconds]
        'login'         => [5, 900],
        'password'      => [3, 900],
        'api'           => [120, 60],
        'api_auth'      => [10, 60],
        'ticket_create' => [10, 600],
    ],
    'password' => [
        'algo'    => PASSWORD_DEFAULT,
        'options' => ['cost' => 12],
    ],
    'headers' => [
        'X-Frame-Options'        => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
    ],
];
