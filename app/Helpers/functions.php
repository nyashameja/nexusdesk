<?php

declare(strict_types=1);

use ParagonHostOps\Core\Config;
use ParagonHostOps\Core\Container;

/**
 * Global helper functions.
 *
 * Kept intentionally small. Anything stateful is resolved through the
 * application container rather than global variables.
 */

if (!function_exists('env')) {
    /**
     * Read an environment variable with an optional default.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('env_bool')) {
    /**
     * Read an environment variable as a strict boolean.
     */
    function env_bool(string $key, bool $default = false): bool
    {
        $value = env($key);

        if ($value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            '1', 'true', 'yes', 'on'  => true,
            '0', 'false', 'no', 'off' => false,
            default                   => $default,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Read a dot-notation configuration value, e.g. config('whm.host').
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('app')) {
    /**
     * Resolve a service from the application container.
     */
    function app(?string $id = null): mixed
    {
        $container = Container::instance();

        return $id === null ? $container : $container->get($id);
    }
}

if (!function_exists('e')) {
    /**
     * Escape a value for safe HTML output (XSS protection).
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);
        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('url')) {
    /**
     * Build an absolute URL relative to the configured APP_URL.
     */
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve a previously submitted form value flashed to the session.
     */
    function old(string $key, string $default = ''): string
    {
        $old = $_SESSION['_old'] ?? [];
        return isset($old[$key]) ? e($old[$key]) : $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \ParagonHostOps\Core\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}
