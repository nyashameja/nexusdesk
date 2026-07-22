<?php

declare(strict_types=1);

use ParagonHostOps\Controllers\AccountsController;
use ParagonHostOps\Controllers\AuthController;
use ParagonHostOps\Controllers\ClientsController;
use ParagonHostOps\Controllers\DashboardController;
use ParagonHostOps\Controllers\DomainsController;
use ParagonHostOps\Controllers\SettingsController;
use ParagonHostOps\Controllers\SslController;
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

    // --- Clients (CRM) ---
    $router->get('/clients', [ClientsController::class, 'index'], [AuthMiddleware::class, 'perm:clients.view']);
    $router->get('/clients/create', [ClientsController::class, 'create'], [AuthMiddleware::class, 'perm:clients.manage']);
    $router->post('/clients', [ClientsController::class, 'store'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:clients.manage']);
    $router->get('/clients/{id}/edit', [ClientsController::class, 'edit'], [AuthMiddleware::class, 'perm:clients.manage']);
    $router->add('PUT', '/clients/{id}', [ClientsController::class, 'update'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:clients.manage']);
    $router->post('/clients/{id}/notes', [ClientsController::class, 'addNote'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:clients.manage']);
    $router->post('/clients/{id}/link-account', [ClientsController::class, 'linkAccount'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:clients.manage']);
    $router->add('DELETE', '/clients/{id}/accounts/{accountId}/unlink', [ClientsController::class, 'unlinkAccount'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:clients.manage']);
    $router->get('/clients/{id}', [ClientsController::class, 'show'], [AuthMiddleware::class, 'perm:clients.view']);

    // --- Domains (local registry) ---
    $router->get('/domains', [DomainsController::class, 'index'], [AuthMiddleware::class, 'perm:domains.view']);
    $router->get('/domains/create', [DomainsController::class, 'create'], [AuthMiddleware::class, 'perm:domains.manage']);
    $router->post('/domains', [DomainsController::class, 'store'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:domains.manage']);
    $router->get('/domains/{id}/edit', [DomainsController::class, 'edit'], [AuthMiddleware::class, 'perm:domains.manage']);
    $router->add('PUT', '/domains/{id}', [DomainsController::class, 'update'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:domains.manage']);
    $router->get('/domains/{id}', [DomainsController::class, 'show'], [AuthMiddleware::class, 'perm:domains.view']);

    // --- SSL Centre (read-only) ---
    $router->get('/ssl', [SslController::class, 'index'], [AuthMiddleware::class, 'perm:ssl.view']);

    // --- Synchronisation (read-only) ---
    $router->get('/sync', [SyncController::class, 'index'], [AuthMiddleware::class, 'perm:sync.view']);
    $router->get('/sync/history', [SyncController::class, 'history'], [AuthMiddleware::class, 'perm:sync.view']);
    $router->post('/sync/run', [SyncController::class, 'run'], [VerifyCsrfMiddleware::class, AuthMiddleware::class, 'perm:sync.run']);

    // --- Settings ---
    $router->get('/settings/whm', [SettingsController::class, 'whm'], [AuthMiddleware::class, 'perm:settings.view']);
};
