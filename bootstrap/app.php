<?php

declare(strict_types=1);

use ParagonHostOps\Core\App;
use ParagonHostOps\Core\Container;
use ParagonHostOps\Core\Csrf;
use ParagonHostOps\Core\Router;
use ParagonHostOps\Core\Session;

/**
 * Web application bootstrap.
 *
 * Builds the shared container, then adds the web-specific concerns: session
 * start, CSRF token priming, route registration, and the HTTP kernel.
 */

/** @var Container $container */
$container = require __DIR__ . '/container.php';

// Session must be active before a CSRF token is issued.
$container->get(Session::class);
Csrf::token();

// --- Router ---
$router = new Router($container);
(require __DIR__ . '/../routes/web.php')($router);
(require __DIR__ . '/../routes/api.php')($router);

return new App($container, $router);
