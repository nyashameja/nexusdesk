<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

/**
 * PSR-3-style logger interface. Kept local (no PSR dependency) but signature
 * compatible so it can be swapped for a PSR-3 logger later.
 */
interface LoggerInterface
{
    /** @param array<string,mixed> $context */
    public function emergency(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function alert(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function critical(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function notice(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void;
    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void;
}
