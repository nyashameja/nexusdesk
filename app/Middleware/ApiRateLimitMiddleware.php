<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\JsonResponse;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\Security\RateLimiter;
use Closure;

/**
 * Per-token (or per-IP) rate limiting for the API. Emits X-RateLimit-* headers
 * on every response and 429 with Retry-After when the window is exhausted.
 */
final class ApiRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        [$limit, $window] = (array) config('security.rate_limits.api', [120, 60]);
        $identifier = $request->bearerToken() ?? $request->ip();
        $key = 'api:' . substr(hash('sha256', (string) $identifier), 0, 32);

        $result = $this->limiter->hit($key, (int) $limit, (int) $window);

        if (!$result['allowed']) {
            return JsonResponse::error('rate_limited', 'Too many requests.', 429)
                ->withHeaders($this->headers($result) + ['Retry-After' => (string) $result['retry_after']]);
        }

        return $next($request)->withHeaders($this->headers($result));
    }

    /** @param array<string,int> $result @return array<string,string> */
    private function headers(array $result): array
    {
        return [
            'X-RateLimit-Limit'     => (string) $result['limit'],
            'X-RateLimit-Remaining' => (string) $result['remaining'],
            'X-RateLimit-Reset'     => (string) $result['reset'],
        ];
    }
}
