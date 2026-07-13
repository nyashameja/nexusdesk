<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight .env reader.
 *
 * If vlucas/phpdotenv is installed it is used (richer validation); otherwise
 * this parser loads KEY=VALUE pairs into a static store. Values are never
 * written to $_ENV/$_SERVER by this parser to keep secrets out of superglobals
 * that get dumped in error pages.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // strip surrounding quotes
            if (strlen($value) >= 2
                && (($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$vars[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            'empty' => '',
            default => $value,
        };
    }

    public static function has(string $key): bool
    {
        return isset(self::$vars[$key]) || getenv($key) !== false;
    }
}
