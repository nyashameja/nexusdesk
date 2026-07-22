<?php

declare(strict_types=1);

namespace ParagonHostOps\Middleware;

use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Services\Auth;

/**
 * Prevents authenticated users from viewing guest-only pages (e.g. login).
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth)
    {
    }

    public function handle(Request $request, array $params): ?Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url('/dashboard'));
        }

        return null;
    }
}
