<?php

declare(strict_types=1);

namespace ParagonHostOps\Middleware;

use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Services\Auth;

/**
 * Requires an authenticated session. Unauthenticated requests are redirected
 * to the login page (or receive 401 JSON for API calls).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth)
    {
    }

    public function handle(Request $request, array $params): ?Response
    {
        if ($this->auth->check()) {
            return null;
        }

        if ($request->wantsJson()) {
            return Response::json(['error' => 'Authentication required.'], 401);
        }

        return Response::redirect(url('/login'));
    }
}
