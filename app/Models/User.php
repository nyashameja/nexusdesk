<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Typed user entity. Hydrated from a DB row by the repository. Carries the
 * resolved role slug and the effective permission set for fast gate checks.
 */
final class User
{
    /** @param string[] $permissions */
    public function __construct(
        public readonly int $id,
        public readonly int $roleId,
        public readonly ?int $companyId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly bool $isActive,
        public readonly string $roleSlug = 'guest',
        public readonly array $permissions = [],
        public readonly ?string $avatarPath = null,
        public readonly bool $twoFactorEnabled = false,
        public readonly string $theme = 'system',
        public readonly ?string $timezone = null,
    ) {
    }

    /** @param array<string,mixed> $row @param string[] $permissions */
    public static function fromRow(array $row, string $roleSlug = 'guest', array $permissions = []): self
    {
        return new self(
            id: (int) $row['id'],
            roleId: (int) $row['role_id'],
            companyId: isset($row['company_id']) ? (int) $row['company_id'] : null,
            firstName: (string) $row['first_name'],
            lastName: (string) $row['last_name'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            isActive: (bool) $row['is_active'],
            roleSlug: $roleSlug,
            permissions: $permissions,
            avatarPath: $row['avatar_path'] ?? null,
            twoFactorEnabled: (bool) ($row['two_factor_enabled'] ?? false),
            theme: (string) ($row['theme'] ?? 'system'),
            timezone: $row['timezone'] ?? null,
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->firstName, 0, 1) . mb_substr($this->lastName, 0, 1));
    }

    public function isAdmin(): bool
    {
        return $this->roleSlug === 'administrator';
    }

    public function isManager(): bool
    {
        return $this->roleSlug === 'manager';
    }

    public function isAgent(): bool
    {
        return $this->roleSlug === 'agent';
    }

    public function isCustomer(): bool
    {
        return $this->roleSlug === 'customer';
    }

    /** Staff = agent, manager, or admin (access to the desk). */
    public function isStaff(): bool
    {
        return in_array($this->roleSlug, ['agent', 'manager', 'administrator'], true);
    }

    public function can(string $permission): bool
    {
        return $this->isAdmin() || in_array($permission, $this->permissions, true);
    }

    public function homePath(): string
    {
        return match ($this->roleSlug) {
            'administrator' => '/admin',
            'manager'       => '/manage',
            'agent'         => '/desk',
            'customer'      => '/portal',
            default         => '/',
        };
    }
}
