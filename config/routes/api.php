<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Api\V1\TicketController as ApiTicketController;
use App\Controllers\Api\V1\MeController;
use App\Middleware\ApiAuthMiddleware;

/**
 * REST API v1 routes. Stateless bearer-token auth, no CSRF/sessions.
 *
 * @var Router $router
 */
$router->group(['prefix' => '/api/v1', 'middleware' => [ApiAuthMiddleware::class]], function (Router $router): void {
    $router->get('/auth/me', [MeController::class, 'show']);
    $router->get('/tickets', [ApiTicketController::class, 'index']);
    $router->get('/tickets/{id}', [ApiTicketController::class, 'show']);
});
