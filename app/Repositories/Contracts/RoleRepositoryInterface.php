<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface RoleRepositoryInterface
{
    /** @return array<int,array<string,mixed>> */
    public function all(): array;
    public function findBySlug(string $slug): ?array;
    /** @return array<int,array<string,mixed>> */
    public function allPermissions(): array;
}
