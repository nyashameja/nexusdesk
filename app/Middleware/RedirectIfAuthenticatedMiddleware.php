<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth\AuthContext;
use Closure;

/**
 * Keeps authenticated users away from guest-only pages (login, register),
 * sending them to their role home instead.
 */
final class RedirectIfAuthenticatedMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = AuthContext::user();
        if ($user !== null) {
            return new RedirectResponse($user->homePath());
        }
        return $next($request);
    }
}
