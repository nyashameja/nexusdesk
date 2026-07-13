<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface CompanyRepositoryInterface
{
    public function find(int $id): ?array;
    /** @return array<int,array<string,mixed>> */
    public function paginate(int $page, int $perPage, ?string $search = null): array;
    public function count(?string $search = null): int;
    /** @param array<string,mixed> $data */
    public function create(array $data): int;
}
