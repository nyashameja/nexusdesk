<?php

declare(strict_types=1);

namespace ParagonHostOps\Core\Exceptions;

use RuntimeException;

/**
 * Represents an HTTP-level error (404, 403, 405, 500, ...) that the front
 * controller renders as a friendly error page.
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        private int $statusCode = 500,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Authentication required.',
            403 => 'You do not have permission to view this page.',
            404 => 'The page you requested could not be found.',
            405 => 'Method not allowed.',
            419 => 'Your session has expired. Please try again.',
            default => 'Something went wrong.',
        };
    }
}
