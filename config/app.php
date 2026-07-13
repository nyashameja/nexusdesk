<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name'          => Env::get('APP_NAME', 'NexusDesk'),
    'env'           => Env::get('APP_ENV', 'production'),
    'debug'         => (bool) Env::get('APP_DEBUG', false),
    'url'           => Env::get('APP_URL', 'http://localhost'),
    'key'           => Env::get('APP_KEY', ''),
    'timezone'      => Env::get('APP_TIMEZONE', 'UTC'),
    'asset_version' => '1.0.0',
];
