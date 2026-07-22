<?php

declare(strict_types=1);

namespace ParagonHostOps\Middleware;

use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Core\Exceptions\HttpException;
use ParagonHostOps\Services\Auth;

/**
 * Server-side authorisation gate. Every protected route declares the exact
 * permission it needs (perm:<slug>) — authorisation is never left to hidden
 * UI buttons alone.
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Auth $auth,
        private string $permission,
    ) {
    }

    public function handle(Request $request, array $params): ?Response
    {
        if (!$this->auth->check()) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Authentication required.'], 401);
            }
            return Response::redirect(url('/login'));
        }

        if ($this->auth->can($this->permission)) {
            return null;
        }

        if ($request->wantsJson()) {
            return Response::json(['error' => 'You do not have permission to perform this action.'], 403);
        }

        throw new HttpException(403);
    }
}
