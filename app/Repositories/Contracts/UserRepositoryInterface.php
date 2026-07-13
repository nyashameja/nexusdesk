<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    /** @return array<int,array<string,mixed>> */
    public function paginate(int $page, int $perPage, ?string $search = null, ?string $roleSlug = null): array;
    public function count(?string $search = null, ?string $roleSlug = null): int;
    /** @param array<string,mixed> $data */
    public function create(array $data): int;
    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void;
    public function updatePassword(int $id, string $passwordHash): void;
    public function recordLogin(int $id, string $ip): void;
    /** @return string[] Effective permission slugs for the user's role. */
    public function permissionsForRole(int $roleId): array;
    public function roleSlug(int $roleId): string;
}
