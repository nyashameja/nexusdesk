<?php

declare(strict_types=1);

namespace ParagonHostOps\Middleware;

use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;

/**
 * Middleware contract. Returning a Response short-circuits the request;
 * returning null allows it to continue to the next middleware / controller.
 */
interface MiddlewareInterface
{
    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params): ?Response;
}
