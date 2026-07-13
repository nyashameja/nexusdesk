<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AuditRepositoryInterface
{
    /** @param array<string,mixed> $data */
    public function record(array $data): void;
    /** @return array<int,array<string,mixed>> */
    public function paginate(int $page, int $perPage): array;
    public function count(): int;
}
