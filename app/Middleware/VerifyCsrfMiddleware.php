<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Support\Security\Csrf;
use Closure;

/**
 * Verifies the CSRF token on state-changing web requests. Reads the token from
 * the `_token` field or the `X-CSRF-Token` header (for AJAX).
 */
final class VerifyCsrfMiddleware implements MiddlewareInterface
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), self::READ_METHODS, true)) {
            $token = $request->input('_token') ?? $request->header('X-CSRF-Token');
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                throw HttpException::tokenMismatch();
            }
        }
        return $next($request);
    }
}
