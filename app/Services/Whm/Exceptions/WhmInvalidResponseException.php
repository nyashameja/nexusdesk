<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm\Exceptions;

use Throwable;

/**
 * Thrown when the WHM response is not valid JSON or is missing the expected
 * metadata envelope.
 */
final class WhmInvalidResponseException extends WhmException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            $message,
            'WHM returned an unexpected response.',
            $code,
            $previous
        );
    }
}
