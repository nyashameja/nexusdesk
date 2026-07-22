<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm\Exceptions;

use Throwable;

/**
 * Thrown when the token authenticates but lacks the privilege to call a
 * particular function. The dashboard degrades gracefully rather than crashing.
 */
final class WhmPermissionException extends WhmException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            $message,
            'This information is unavailable with the current WHM permissions.',
            $code,
            $previous
        );
    }
}
