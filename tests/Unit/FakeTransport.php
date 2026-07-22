<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Services\Whm\WhmTransportInterface;

/**
 * Test double that returns queued responses, recording how many calls were
 * made so retry behaviour can be asserted.
 */
final class FakeTransport implements WhmTransportInterface
{
    public int $calls = 0;

    /** @var array<int, array{status:int, body:string, error:?string, timedOut:bool, connectFailed:bool}> */
    private array $queue;

    /**
     * @param array<int, array{status:int, body:string, error:?string, timedOut:bool, connectFailed:bool}> $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public static function ok(string $body): self
    {
        return new self([self::response(200, $body)]);
    }

    public static function status(int $status, string $body = ''): self
    {
        return new self([self::response($status, $body)]);
    }

    public static function timeout(): self
    {
        return new self([['status' => 0, 'body' => '', 'error' => 'timed out', 'timedOut' => true, 'connectFailed' => false]]);
    }

    /**
     * @return array{status:int, body:string, error:?string, timedOut:bool, connectFailed:bool}
     */
    public static function response(int $status, string $body): array
    {
        return ['status' => $status, 'body' => $body, 'error' => null, 'timedOut' => false, 'connectFailed' => false];
    }

    public function get(string $url, array $headers, array $query): array
    {
        $this->calls++;
        // Return the queued response for this call, or repeat the last one.
        return $this->queue[$this->calls - 1] ?? end($this->queue);
    }
}
