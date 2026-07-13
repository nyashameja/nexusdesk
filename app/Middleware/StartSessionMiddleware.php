<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Support\Security\Session;
use Closure;

/**
 * Starts the secure session for web requests (idle + absolute timeouts applied).
 */
final class StartSessionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $idle = (int) config('security.session.idle', 30);
        $lifetime = (int) config('security.session.lifetime', 120);
        Session::start($request->isSecure(), $idle, $lifetime);

        return $next($request);
    }
}
