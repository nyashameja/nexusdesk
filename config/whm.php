<?php

declare(strict_types=1);

/**
 * WHM / cPanel API configuration.
 *
 * The API token is read from the environment only. It is never rendered in
 * views, exposed to JavaScript, returned by API responses, or written to logs.
 */
return [
    'host'            => env('WHM_HOST', ''),
    'port'            => (int) env('WHM_PORT', 2087),
    'username'        => env('WHM_USERNAME', ''),
    'token'           => env('WHM_API_TOKEN', ''),
    'verify_ssl'      => env_bool('WHM_VERIFY_SSL', true),
    'timeout'         => (int) env('WHM_TIMEOUT', 30),
    'connect_timeout' => (int) env('WHM_CONNECT_TIMEOUT', 10),
    'mock_mode'       => env_bool('WHM_MOCK_MODE', false),

    'api_version' => 1,

    /**
     * WHM API 1 functions this application relies on for Version 1.
     * All are read-only. The capability checker probes these against the
     * privileges granted to the configured token.
     */
    'functions' => [
        'listaccts'             => 'List hosting accounts',
        'accountsummary'        => 'Account summary detail',
        'listpkgs'              => 'List hosting packages',
        'showbw'                => 'Bandwidth usage',
        'fetch_ssl_vhosts'      => 'SSL certificate vhosts',
        'get_server_information'=> 'Server information',
        'servicestatus'         => 'Service status',
        'listsuspended'         => 'List suspended accounts',
        'version'               => 'WHM version',
    ],

    // Cautious retry policy for transient (network / 5xx) failures only.
    'retry' => [
        'max_attempts' => 2,
        'base_delay_ms' => 250,
    ],
];
