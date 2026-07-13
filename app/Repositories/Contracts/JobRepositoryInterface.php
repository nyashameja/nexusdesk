<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface JobRepositoryInterface
{
    /** @param array<string,mixed> $payload */
    public function enqueue(string $type, array $payload, string $queue = 'default', int $delaySeconds = 0): int;

    /** Reserve the next available job (atomically), or null if none. @return array<string,mixed>|null */
    public function reserveNext(): ?array;

    public function markDone(int $id): void;
    public function markFailed(int $id, string $error, bool $retry): void;

    public function pendingCount(): int;
    public function failedCount(): int;
}
