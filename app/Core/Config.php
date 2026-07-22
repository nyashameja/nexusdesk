<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

/**
 * Configuration repository.
 *
 * Loads the PHP files in /config once and exposes dot-notation access, e.g.
 * Config::get('whm.host'). Values are read-only at runtime.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    public static function load(string $configDir): void
    {
        foreach (glob(rtrim($configDir, '/') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            self::$items[$name] = require $file;
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            return $default;
        }

        $segments = explode('.', $key);
        $value    = self::$items;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Test-support helper: inject configuration without loading files.
     *
     * @param array<string, mixed> $items
     */
    public static function set(array $items): void
    {
        self::$items = array_merge(self::$items, $items);
        self::$loaded = true;
    }
}
