<?php

declare(strict_types=1);

/**
 * Database connection configuration (PDO / MySQL).
 */
return [
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => (int) env('DB_PORT', 3306),
    'database' => env('DB_DATABASE', 'paragon_hostops'),
    'username' => env('DB_USERNAME', ''),
    'password' => env('DB_PASSWORD', ''),
    'charset'  => env('DB_CHARSET', 'utf8mb4'),

    'options' => [
        // Throw exceptions on error, fetch associative arrays, use real prepares.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
];
