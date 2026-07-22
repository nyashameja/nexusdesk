<?php

declare(strict_types=1);

namespace ParagonHostOps\Middleware;

use ParagonHostOps\Core\Csrf;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Response;
use ParagonHostOps\Core\Exceptions\HttpException;

/**
 * Validates the CSRF token on all state-changing (POST/PUT/PATCH/DELETE)
 * requests. The token may arrive as the _csrf form field or the
 * X-CSRF-Token header.
 */
final class VerifyCsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, array $params): ?Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        $token = (string) ($request->input('_csrf', '') ?: ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

        if (Csrf::validate($token)) {
            return null;
        }

        if ($request->wantsJson()) {
            return Response::json(['error' => 'Invalid or expired CSRF token.'], 419);
        }

        throw new HttpException(419);
    }
}
