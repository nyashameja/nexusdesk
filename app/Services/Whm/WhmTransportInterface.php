<?php

declare(strict_types=1);

namespace ParagonHostOps\Services\Whm;

/**
 * Low-level HTTP transport contract for WHM API calls.
 *
 * Abstracting the transport lets the client be unit-tested (and mocked for
 * local development) without touching the network.
 */
interface WhmTransportInterface
{
    /**
     * Perform a GET request.
     *
     * @param array<string, string> $headers
     * @param array<string, scalar> $query
     * @return array{status:int, body:string, error:?string, timedOut:bool, connectFailed:bool}
     */
    public function get(string $url, array $headers, array $query): array;
}
