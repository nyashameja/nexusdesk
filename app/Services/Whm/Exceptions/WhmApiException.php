<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm\Exceptions;

use Throwable;

/**
 * Thrown when WHM returns a well-formed response whose metadata.result
 * indicates failure (a logical API error rather than a transport problem).
 */
final class WhmApiException extends WhmException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            $message,
            'WHM reported an error while processing the request.',
            $code,
            $previous
        );
    }
}
