<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PSR-4 autoloader.
 *
 * Used so the application boots on hosts where `composer install` has not been
 * run for the core namespace. When the Composer autoloader is present it is
 * loaded first (in bootstrap.php) to provide third-party packages; this loader
 * then covers the App\ namespace as a fallback.
 */
final class Autoloader
{
    /** @var array<string,string> namespace prefix => base directory */
    private array $prefixes = [];

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $this->prefixes[$prefix] = rtrim($baseDir, '/\\') . '/';
    }

    public function load(string $class): bool
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return true;
            }
        }
        return false;
    }
}
