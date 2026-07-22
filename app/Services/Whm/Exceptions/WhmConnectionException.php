<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm\Exceptions;

use Throwable;

/**
 * Thrown when the WHM host is unreachable, times out, or the TLS handshake
 * fails. These failures are eligible for cautious retries.
 */
final class WhmConnectionException extends WhmException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            $message,
            'The WHM server could not be reached. Please try again shortly.',
            $code,
            $previous
        );
    }
}
