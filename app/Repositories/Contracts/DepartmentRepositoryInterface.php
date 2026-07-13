<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface DepartmentRepositoryInterface
{
    /** @return array<int,array<string,mixed>> */
    public function all(bool $publicOnly = false): array;
    public function find(int $id): ?array;
    /** @param array<string,mixed> $data */
    public function create(array $data): int;
    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void;
    public function defaultSlaPolicyId(int $departmentId): ?int;
}
