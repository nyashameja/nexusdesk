<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Session facade with secure cookie params, idle + absolute timeout, and
 * fixation regeneration. Flash data survives exactly one subsequent request.
 */
final class Session
{
    public static function start(bool $secure, int $idleMinutes = 30, int $lifetimeMinutes = 120): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('nexusdesk_session');
        session_start();

        self::enforceTimeouts($idleMinutes, $lifetimeMinutes);

        // Age out flash data (available for the request after it was set).
        if (isset($_SESSION['_flash_new'])) {
            $_SESSION['_flash'] = $_SESSION['_flash_new'];
            unset($_SESSION['_flash_new']);
        } else {
            unset($_SESSION['_flash']);
        }
    }

    private static function enforceTimeouts(int $idleMinutes, int $lifetimeMinutes): void
    {
        $now = time();
        $started = $_SESSION['_started_at'] ?? $now;
        $lastSeen = $_SESSION['_last_seen'] ?? $now;

        $idleExpired = ($now - $lastSeen) > ($idleMinutes * 60);
        $absoluteExpired = ($now - $started) > ($lifetimeMinutes * 60);

        if (isset($_SESSION['user_id']) && ($idleExpired || $absoluteExpired)) {
            self::invalidate();
            return;
        }

        $_SESSION['_started_at'] ??= $now;
        $_SESSION['_last_seen'] = $now;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash'][$key] ?? $default;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function invalidate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
