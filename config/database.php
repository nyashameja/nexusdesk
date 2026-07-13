<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host'     => Env::get('DB_HOST', 'localhost'),
    'port'     => (int) Env::get('DB_PORT', 3306),
    'database' => Env::get('DB_DATABASE', 'nexusdesk'),
    'username' => Env::get('DB_USERNAME', 'root'),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset'  => Env::get('DB_CHARSET', 'utf8mb4'),
];
