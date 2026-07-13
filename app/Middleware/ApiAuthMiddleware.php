<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\JsonResponse;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\AuthContext;
use Closure;

/**
 * Stateless bearer-token authentication for the REST API. Looks up the hashed
 * token, loads the owner into AuthContext, and updates last-used. No sessions,
 * no CSRF.
 */
final class ApiAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Database $db,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return JsonResponse::error('unauthenticated', 'Bearer token required.', 401);
        }

        $hash = hash('sha256', $token);
        $row = $this->db->selectOne(
            'SELECT user_id FROM api_tokens
             WHERE token_hash = ? AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())',
            [$hash]
        );
        if ($row === null) {
            return JsonResponse::error('unauthenticated', 'Invalid or expired token.', 401);
        }

        $user = $this->users->findById((int) $row['user_id']);
        if ($user === null || !$user->isActive) {
            return JsonResponse::error('unauthenticated', 'Account unavailable.', 401);
        }

        $this->db->run('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?', [$hash]);
        AuthContext::set($user);

        return $next($request);
    }
}
