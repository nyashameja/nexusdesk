<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for all WHM API exceptions.
 *
 * Carries a safe, user-presentable message separately from the technical
 * detail that is only ever written to protected logs.
 */
class WhmException extends RuntimeException
{
    public function __construct(
        string $message,
        private string $safeMessage = 'A WHM API error occurred.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * A message safe to show to end users (never contains credentials or
     * raw API payloads).
     */
    public function safeMessage(): string
    {
        return $this->safeMessage;
    }
}
