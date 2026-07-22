<?php

declare(strict_types=1);

/**
 * Front controller. The web server document root points here (public/).
 * All requests are routed through the application kernel.
 */

use ParagonHostOps\Core\App;
use ParagonHostOps\Core\Request;

$root = dirname(__DIR__);

/*
 * Pre-flight checks. These turn the two most common deployment failures
 * (dependencies not installed, storage not writable) into a clear message
 * instead of a blank HTTP 500. They run before anything else is loaded.
 */
if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit(
        "Paragon HostOps is not fully installed.\n\n" .
        "Composer dependencies are missing (vendor/ is not present).\n" .
        "Run this once from the application root:\n\n" .
        "    composer install --no-dev --optimize-autoloader\n\n" .
        "See README.md > cPanel deployment for details."
    );
}

foreach (['storage/logs', 'storage/sessions', 'storage/cache'] as $dir) {
    if (!is_dir($root . '/' . $dir) || !is_writable($root . '/' . $dir)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit(
            "Paragon HostOps storage is not writable.\n\n" .
            "The web server user needs write access to: /{$dir}\n" .
            "Fix permissions (e.g. chmod 0770 storage and its subdirectories),\n" .
            "then reload. See README.md > File permissions."
        );
    }
}

// Last-resort fatal handler: if a fatal error escapes the kernel, log nothing
// sensitive and show a clean message rather than a blank page.
register_shutdown_function(static function () use ($root): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    @file_put_contents(
        $root . '/storage/logs/fatal-' . gmdate('Y-m-d') . '.log',
        sprintf("[%s] %s in %s:%d\n", gmdate('c'), $error['message'], $error['file'], $error['line']),
        FILE_APPEND | LOCK_EX
    );

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
        . '<div style="font-family:sans-serif;max-width:520px;margin:80px auto;text-align:center">'
        . '<h1 style="color:#1d4ed8">500</h1><p>An unexpected error occurred. '
        . 'The technical details have been recorded in storage/logs.</p></div>';
});

/** @var App $app */
$app = require $root . '/bootstrap/app.php';

$app->run(Request::capture());
