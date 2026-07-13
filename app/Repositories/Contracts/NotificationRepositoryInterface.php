<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface NotificationRepositoryInterface
{
    /** @param array<string,mixed> $data */
    public function create(array $data): int;
    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, bool $unreadOnly = false, int $limit = 20): array;
    public function unreadCount(int $userId): int;
    public function markRead(int $id, int $userId): void;
    public function markAllRead(int $userId): void;
}
