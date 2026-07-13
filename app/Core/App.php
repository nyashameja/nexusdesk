<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Static access point to the container and config. Set once during bootstrap.
 * Keeps helper functions simple without turning the whole app into globals.
 */
final class App
{
    private static ?Container $container = null;
    private static ?Config $config = null;
    private static string $basePath = '';

    public static function boot(Container $container, Config $config, string $basePath): void
    {
        self::$container = $container;
        self::$config = $config;
        self::$basePath = rtrim($basePath, '/');
    }

    public static function container(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException('Application container not booted.');
        }
        return self::$container;
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        return self::$config?->get($key, $default) ?? $default;
    }

    public static function basePath(string $path = ''): string
    {
        return self::$basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public static function storagePath(string $path = ''): string
    {
        return self::basePath('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    public static function isDebug(): bool
    {
        return (bool) self::config('app.debug', false);
    }
}
