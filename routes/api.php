<?php

declare(strict_types=1);

use ParagonHostOps\Controllers\SettingsController;
use ParagonHostOps\Core\Router;
use ParagonHostOps\Middleware\AuthMiddleware;
use ParagonHostOps\Middleware\VerifyCsrfMiddleware;

/**
 * Internal AJAX / JSON API routes. All are backend-only; the browser never
 * talks to WHM directly — these endpoints proxy through the PHP services.
 */
return static function (Router $router): void {
    // WHM connection test (read-only). CSRF-protected because it is a POST
    // action triggered from the settings page.
    $router->post(
        '/api/whm/test-connection',
        [SettingsController::class, 'testConnection'],
        [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:settings.view']
    );

    $router->post(
        '/api/whm/capabilities',
        [SettingsController::class, 'capabilities'],
        [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:settings.view']
    );
};
