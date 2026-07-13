<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host'       => Env::get('MAIL_HOST', 'localhost'),
    'port'       => (int) Env::get('MAIL_PORT', 587),
    'username'   => Env::get('MAIL_USERNAME', ''),
    'password'   => Env::get('MAIL_PASSWORD', ''),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
    'from'       => [
        'address' => Env::get('MAIL_FROM_ADDRESS', 'support@example.com'),
        'name'    => Env::get('MAIL_FROM_NAME', 'NexusDesk Support'),
    ],
];
