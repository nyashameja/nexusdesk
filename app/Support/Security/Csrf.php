<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Per-session CSRF token. Verified by CsrfMiddleware on state-changing web
 * requests (POST/PUT/PATCH/DELETE). Comparison is constant-time.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(?string $token): bool
    {
        if (!is_string($token) || empty($_SESSION['_csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $token);
    }
}
