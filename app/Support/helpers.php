<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\View;

/**
 * Global helper functions (autoloaded via composer "files").
 * Kept minimal — they delegate to the container/service singletons.
 */

if (!function_exists('app')) {
    /** Resolve a service from the container (or the container itself). */
    function app(?string $id = null): mixed
    {
        $container = App::container();
        return $id === null ? $container : $container->get($id);
    }
}

if (!function_exists('config')) {
    /** Read a dotted config value, e.g. config('app.name'). */
    function config(string $key, mixed $default = null): mixed
    {
        return App::config($key, $default);
    }
}

if (!function_exists('e')) {
    /** Escape a value for safe HTML output. */
    function e(mixed $value): string
    {
        return View::e($value);
    }
}

if (!function_exists('view')) {
    /** @param array<string,mixed> $data */
    function view(string $template, array $data = [], ?string $layout = 'layouts/app'): \App\Core\Response
    {
        return View::make($template, $data, $layout);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to): \App\Core\RedirectResponse
    {
        return new \App\Core\RedirectResponse($to);
    }
}

if (!function_exists('old')) {
    /** Retrieve a flashed old-input value after a validation redirect. */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_flash']['old'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Support\Security\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('auth')) {
    /** The authenticated user (or null). */
    function auth(): ?\App\Models\User
    {
        return \App\Services\Auth\AuthContext::user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return \App\Services\Auth\AuthContext::can($permission);
    }
}

if (!function_exists('url')) {
    /** @param array<string,string|int> $params */
    function url(string $name, array $params = []): string
    {
        return App::container()->get(\App\Core\Router::class)->url($name, $params);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $version = config('app.asset_version', '1');
        return '/assets/' . ltrim($path, '/') . '?v=' . $version;
    }
}

if (!function_exists('setting')) {
    /** Read a runtime setting (group.key), falling back to $default. */
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Services\Settings\Settings::get($key, $default);
    }
}
