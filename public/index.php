<?php

declare(strict_types=1);

/**
 * NexusDesk front controller. Every request routes through here.
 */

use App\Core\Request;

// Serve existing static files directly under PHP's built-in dev server.
// (Under Apache this is handled by public/.htaccess; this block is a no-op.)
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

// Redirect to the installer until the app is set up.
$lock = dirname(__DIR__) . '/storage/installed.lock';
$env  = dirname(__DIR__) . '/.env';
if (!is_file($lock) && !is_file($env) && !str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/installer')) {
    if (is_file(__DIR__ . '/installer.php')) {
        header('Location: /installer.php');
        exit;
    }
}

/** @var App\Core\Kernel $kernel */
$kernel = require dirname(__DIR__) . '/app/bootstrap.php';

$request = Request::capture();
$kernel->handle($request)->send();
