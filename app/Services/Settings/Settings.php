<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Repositories\Contracts\SettingRepositoryInterface;

/**
 * Runtime settings accessor. Loads all settings once per request and exposes
 * them by "group.key". Backed by the settings table; used for branding,
 * timezone, ticket prefix, feature flags, etc.
 */
final class Settings
{
    /** @var array<string,mixed>|null */
    private static ?array $cache = null;
    private static ?SettingRepositoryInterface $repository = null;

    public static function boot(SettingRepositoryInterface $repository): void
    {
        self::$repository = $repository;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            try {
                self::$cache = self::$repository?->all() ?? [];
            } catch (\Throwable) {
                // Database not yet available (e.g. pre-install): fall back to
                // config-file defaults rather than failing the whole page.
                self::$cache = [];
            }
        }
        return self::$cache[$key] ?? $default;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
