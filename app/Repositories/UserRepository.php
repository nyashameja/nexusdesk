<?php

declare(strict_types=1);

namespace ParagonHostOps\Repositories;

use ParagonHostOps\Core\Database;

/**
 * Data access for application users and their roles/permissions.
 */
final class UserRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->db->first(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [strtolower(trim($email))]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->first(
            'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    /**
     * Return the distinct permission slugs granted to a user via their roles.
     *
     * @return array<int, string>
     */
    public function permissionsFor(int $userId): array
    {
        $rows = $this->db->all(
            'SELECT DISTINCT p.slug
               FROM permissions p
               JOIN role_permissions rp ON rp.permission_id = p.id
               JOIN user_roles ur       ON ur.role_id = rp.role_id
              WHERE ur.user_id = ?',
            [$userId]
        );

        return array_map(static fn (array $r): string => (string) $r['slug'], $rows);
    }

    /**
     * @return array<int, string> role slugs
     */
    public function rolesFor(int $userId): array
    {
        $rows = $this->db->all(
            'SELECT r.slug
               FROM roles r
               JOIN user_roles ur ON ur.role_id = r.id
              WHERE ur.user_id = ?',
            [$userId]
        );

        return array_map(static fn (array $r): string => (string) $r['slug'], $rows);
    }

    public function updateLastLogin(int $userId, string $ip): void
    {
        $this->db->execute(
            'UPDATE users SET last_login_at = UTC_TIMESTAMP(), last_login_ip = ? WHERE id = ?',
            [$ip, $userId]
        );
    }

    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }

        return strtotime((string) $user['locked_until'] . ' UTC') > time();
    }
}
