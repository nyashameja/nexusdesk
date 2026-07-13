<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

/**
 * Daily-rotating file logger. Writes JSON-lines to storage/logs/<channel>-YYYY-MM-DD.log.
 * Secrets in context are scrubbed by key name.
 */
final class FileLogger implements LoggerInterface
{
    private const SECRET_KEYS = ['password', 'password_hash', 'token', 'secret', 'access_token', 'refresh_token', 'api_key'];

    public function __construct(
        private readonly string $directory,
        private readonly string $channel = 'app',
    ) {
    }

    public function emergency(string $message, array $context = []): void { $this->log('emergency', $message, $context); }
    public function alert(string $message, array $context = []): void { $this->log('alert', $message, $context); }
    public function critical(string $message, array $context = []): void { $this->log('critical', $message, $context); }
    public function error(string $message, array $context = []): void { $this->log('error', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->log('warning', $message, $context); }
    public function notice(string $message, array $context = []): void { $this->log('notice', $message, $context); }
    public function info(string $message, array $context = []): void { $this->log('info', $message, $context); }
    public function debug(string $message, array $context = []): void { $this->log('debug', $message, $context); }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }
        $file = sprintf('%s/%s-%s.log', rtrim($this->directory, '/'), $this->channel, date('Y-m-d'));
        $entry = [
            'ts'      => date('c'),
            'level'   => $level,
            'message' => $message,
            'context' => $this->scrub($context),
        ];
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function scrub(array $context): array
    {
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::SECRET_KEYS, true)) {
                $context[$key] = '***';
            } elseif (is_array($value)) {
                $context[$key] = $this->scrub($value);
            }
        }
        return $context;
    }
}
