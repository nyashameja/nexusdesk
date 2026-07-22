<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

/**
 * Lightweight file logger with automatic redaction of sensitive values.
 *
 * Tokens, passwords and Authorization headers are stripped before anything is
 * written to disk so secrets never land in log files.
 */
final class Logger
{
    private const REDACTIONS = [
        // Redact "Authorization: whm user:token" style values.
        '/(Authorization:\s*whm\s+[^:\s]+:)[^\s"\']+/i' => '$1[REDACTED]',
        // Redact token / password style key=value or key: value pairs.
        '/((?:api[_-]?token|token|password|secret)\s*[=:]\s*)[^\s&"\']+/i' => '$1[REDACTED]',
    ];

    public function __construct(private string $logDir)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s: %s%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $level,
            $this->redact($message),
            $context ? ' ' . $this->redact($this->encodeContext($context)) : ''
        );

        $file = rtrim($this->logDir, '/') . '/app-' . gmdate('Y-m-d') . '.log';

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0750, true);
        }

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function encodeContext(array $context): string
    {
        return json_encode($context, JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function redact(string $text): string
    {
        return (string) preg_replace(
            array_keys(self::REDACTIONS),
            array_values(self::REDACTIONS),
            $text
        );
    }
}
