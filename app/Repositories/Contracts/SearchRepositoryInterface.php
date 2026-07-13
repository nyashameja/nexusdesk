<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface SearchRepositoryInterface
{
    /**
     * Global search across permitted entities.
     *
     * @return array{tickets:array<int,array<string,mixed>>,companies:array<int,array<string,mixed>>,articles:array<int,array<string,mixed>>,users:array<int,array<string,mixed>>}
     */
    public function global(string $query, int $perType = 5): array;
}
