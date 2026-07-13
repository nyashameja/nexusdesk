<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\RedirectResponse;
use App\Services\Auth\AuthContext;
use App\Support\Security\Session;
use Closure;

/**
 * Requires an authenticated user. Redirects guests to login (remembering the
 * intended URL); returns 401 JSON for API-style requests.
 */
final class AuthenticateMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AuthContext::check()) {
            if ($request->expectsJson()) {
                return \App\Core\JsonResponse::error('unauthenticated', 'Authentication required.', 401);
            }
            Session::put('_intended', $request->path());
            return new RedirectResponse('/login');
        }
        return $next($request);
    }
}
