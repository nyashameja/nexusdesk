<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Adds security headers to every response, including a Content-Security-Policy
 * that permits only self-hosted assets (no external CDNs) and inline styles for
 * runtime theming.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = (array) config('security.headers', []);
        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        $csp = "default-src 'self'; "
            . "img-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self'; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'none'; "
            . "base-uri 'self'; form-action 'self'";
        $response->header('Content-Security-Policy', $csp);

        if ($request->isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
