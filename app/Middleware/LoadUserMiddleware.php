<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth\AuthContext;
use App\Services\Auth\AuthService;
use Closure;

/**
 * Soft-loads the session user into AuthContext (no redirect). Runs on every web
 * request so layouts can render the user/notification state on guest pages too.
 */
final class LoadUserMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        AuthContext::set($this->auth->userFromSession());
        return $next($request);
    }
}
