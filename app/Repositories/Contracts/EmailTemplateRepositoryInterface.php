<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface EmailTemplateRepositoryInterface
{
    public function bySlug(string $slug): ?array;
    /** @return array<int,array<string,mixed>> */
    public function all(): array;
    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void;
    public function find(int $id): ?array;
}
