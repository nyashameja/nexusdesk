<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

/**
 * Session wrapper with secure cookie parameters, idle-timeout enforcement and
 * fixation protection helpers.
 */
final class Session
{
    private bool $started = false;

    /**
     * @param array<string, mixed> $config The app.session config array.
     */
    public function __construct(private array $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        $path = $this->config['path'] ?? null;
        if (is_string($path) && is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }

        session_name($this->config['name'] ?? 'paragon_session');

        session_set_cookie_params([
            'lifetime' => 0, // session cookie; idle timeout handled below
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) ($this->config['secure_cookie'] ?? false),
            'httponly' => true,
            'samesite' => $this->config['same_site'] ?? 'Lax',
        ]);

        session_start();
        $this->started = true;

        $this->enforceIdleTimeout();
    }

    private function enforceIdleTimeout(): void
    {
        $lifetimeSeconds = (int) ($this->config['lifetime'] ?? 120) * 60;
        $now             = time();
        $last            = $_SESSION['_last_activity'] ?? null;

        if ($last !== null && ($now - (int) $last) > $lifetimeSeconds) {
            $this->invalidate();
            session_start();
            $_SESSION['_expired'] = true;
        }

        $_SESSION['_last_activity'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Regenerate the session ID (call after login to prevent fixation).
     */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** @return array<string, mixed> */
    public function pullAllFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }
}
