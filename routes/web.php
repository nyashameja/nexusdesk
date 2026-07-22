<?php

declare(strict_types=1);

use ParagonHostOps\Controllers\AccountsController;
use ParagonHostOps\Controllers\AuthController;
use ParagonHostOps\Controllers\DashboardController;
use ParagonHostOps\Controllers\SettingsController;
use ParagonHostOps\Controllers\SyncController;
use ParagonHostOps\Core\Router;
use ParagonHostOps\Middleware\AuthMiddleware;
use ParagonHostOps\Middleware\GuestMiddleware;
use ParagonHostOps\Middleware\VerifyCsrfMiddleware;

/**
 * Web (HTML) routes.
 *
 * Middleware pipeline order matters: CSRF verification runs before auth on
 * POST so an expired session still fails closed. Every protected route also
 * declares the specific permission it requires via "perm:<slug>".
 */
return static function (Router $router): void {
    // --- Guest / authentication ---
    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'login'], [VerifyCsrfMiddleware::class, GuestMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout'], [VerifyCsrfMiddleware::class, AuthMiddleware::class]);

    // --- Dashboard ---
    $router->get('/', [DashboardController::class, 'index'], [AuthMiddleware::class, 'perm:dashboard.view']);
    $router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class, 'perm:dashboard.view']);

    // --- Hosting accounts (cached, read-only) ---
    $router->get('/accounts', [AccountsController::class, 'index'], [AuthMiddleware::class, 'perm:accounts.view']);
    $router->get('/accounts/{id}', [AccountsController::class, 'show'], [AuthMiddleware::class, 'perm:accounts.view']);
    $router->post('/accounts/{id}/refresh', [AccountsController::class, 'refresh'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:sync.run']);

    // --- Synchronisation (read-only) ---
    $router->get('/sync', [SyncController::class, 'index'], [AuthMiddleware::class, 'perm:sync.view']);
    $router->get('/sync/history', [SyncController::class, 'history'], [AuthMiddleware::class, 'perm:sync.view']);
    $router->post('/sync/run', [SyncController::class, 'run'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:sync.run']);

    // --- Settings ---
    $router->get('/settings/whm', [SettingsController::class, 'whm'], [AuthMiddleware::class, 'perm:settings.view']);
};
