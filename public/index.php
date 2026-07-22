<?php

declare(strict_types=1);

/**
 * Front controller. The web server document root points here (public/).
 * All requests are routed through the application kernel.
 */

use ParagonHostOps\Core\App;
use ParagonHostOps\Core\Request;

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$app->run(Request::capture());
