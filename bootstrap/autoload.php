<?php

declare(strict_types=1);

/**
 * Class autoloading entry point.
 *
 * Prefers Composer's optimised autoloader when present (vendor/), but this
 * application has NO third-party runtime dependencies, so it also ships a
 * lightweight PSR-4 fallback. That means production can run on shared cPanel
 * hosting without ever running "composer install" — just deploy the files.
 *
 * (Composer is still used for development tooling such as PHPUnit.)
 */

$vendor = __DIR__ . '/../vendor/autoload.php';

if (is_file($vendor)) {
    require $vendor;
    return;
}

// --- Fallback PSR-4 autoloader for the ParagonHostOps\ namespace ---
spl_autoload_register(static function (string $class): void {
    $prefix = 'ParagonHostOps\\';
    $length = strlen($prefix);

    if (strncmp($class, $prefix, $length) !== 0) {
        return;
    }

    $relative = substr($class, $length);
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Global helper functions (Composer would load these via the "files" autoload).
require __DIR__ . '/../app/Helpers/functions.php';
