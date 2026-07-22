<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

/**
 * Minimal .env file loader.
 *
 * Parses KEY=VALUE lines and populates $_ENV / putenv without any external
 * dependency. Supports quoted values, comments and blank lines. Existing
 * real environment variables are never overwritten.
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = self::normalise(trim($value));

            if ($key === '') {
                continue;
            }

            // Do not override variables already present in the real environment.
            if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
                continue;
            }

            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    private static function normalise(string $value): string
    {
        // Strip surrounding matching quotes.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
