<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Exception carrying an HTTP status code, thrown from anywhere to abort a
 * request with a specific response (404, 403, 419, 429, ...).
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Unauthenticated'): self
    {
        return new self(401, $message);
    }

    public static function tokenMismatch(string $message = 'Page expired'): self
    {
        return new self(419, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests', int $retryAfter = 60): self
    {
        return new self(429, $message, ['Retry-After' => (string) $retryAfter]);
    }
}
