<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm\Exceptions;

use Throwable;

/**
 * Thrown when WHM rejects the credentials (HTTP 401/403 access denied).
 * Authentication failures are NEVER retried.
 */
final class WhmAuthenticationException extends WhmException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            $message,
            'WHM authentication failed. Please verify the API token configuration.',
            $code,
            $previous
        );
    }
}
