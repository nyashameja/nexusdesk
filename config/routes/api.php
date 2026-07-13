<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Api\V1\TicketController as ApiTickets;
use App\Controllers\Api\V1\DepartmentController as ApiDepartments;
use App\Controllers\Api\V1\KbController as ApiKb;
use App\Controllers\Api\V1\NotificationController as ApiNotifications;
use App\Controllers\Api\V1\InvoiceController as ApiInvoices;
use App\Controllers\Api\V1\LookupController as ApiLookups;
use App\Controllers\Api\V1\MeController;
use App\Controllers\Api\V1\HealthController;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\ApiRateLimitMiddleware;

/**
 * REST API v1 — stateless bearer-token auth, per-token rate limiting, no
 * CSRF/sessions. See docs/architecture/05-API-SPECIFICATION.md.
 *
 * @var Router $router
 */

// Public: health check (no auth).
$router->get('/api/v1/health', [HealthController::class, 'index']);

$router->group([
    'prefix'     => '/api/v1',
    'middleware' => [ApiRateLimitMiddleware::class, ApiAuthMiddleware::class],
], function (Router $router): void {
    $router->get('/auth/me', [MeController::class, 'show']);

    $router->get('/tickets', [ApiTickets::class, 'index']);
    $router->post('/tickets', [ApiTickets::class, 'store']);
    $router->get('/tickets/{id}', [ApiTickets::class, 'show']);
    $router->post('/tickets/{id}/messages', [ApiTickets::class, 'reply']);

    $router->get('/departments', [ApiDepartments::class, 'index']);
    $router->get('/lookups', [ApiLookups::class, 'index']);

    $router->get('/kb/articles', [ApiKb::class, 'index']);
    $router->get('/kb/articles/{slug}', [ApiKb::class, 'show']);

    $router->get('/invoices', [ApiInvoices::class, 'index']);

    $router->get('/notifications', [ApiNotifications::class, 'index']);
    $router->get('/notifications/unread-count', [ApiNotifications::class, 'unreadCount']);
    $router->post('/notifications/{id}/read', [ApiNotifications::class, 'markRead']);
});
