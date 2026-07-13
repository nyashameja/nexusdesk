<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth\AuthContext;
use Closure;

/**
 * Base role gate. Subclasses declare which role slugs may pass. Assumes
 * AuthenticateMiddleware ran first (user is present).
 */
abstract class RoleGuardMiddleware implements MiddlewareInterface
{
    /** @return string[] */
    abstract protected function allowedRoles(): array;

    public function handle(Request $request, Closure $next): Response
    {
        $user = AuthContext::user();
        if ($user === null || !in_array($user->roleSlug, $this->allowedRoles(), true)) {
            throw HttpException::forbidden('You do not have access to this area.');
        }
        return $next($request);
    }
}
